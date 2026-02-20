import { useEffect, useState } from 'react'
import { adminLeadsApi } from '../../features/leads/api'
import { adminMatchesApi } from '../../features/matches/api'
import { adminPaymentsApi } from '../../features/payments/api'
import { adminRegistrationsApi } from '../../features/registrations/api'
import { adminTournamentsApi } from '../../features/tournaments/api'

const ACTIVE_TOURNAMENT_CODES = new Set([
  'registration_open',
  'registration_closed',
  'in_progress',
  'published',
])

const PAYMENT_PENDING_CODES = new Set([
  'created',
  'pending',
  'requires_action',
  'processing',
])

const ACCEPTED_CODES = new Set(['accepted', 'payment_pending', 'paid'])

const formatDateRange = (start, end) => {
  if (!start && !end) return 'Por definir'
  const format = (value) => {
    if (!value) return ''
    const parsed = new Date(value)
    if (Number.isNaN(parsed.getTime())) return String(value)
    return parsed.toLocaleDateString('es-ES', { month: 'short', day: 'numeric' })
  }
  const left = format(start)
  const right = format(end)
  if (left && right) return `${left} → ${right}`
  return left || right
}

const formatDateTimeRange = (start, end) => {
  if (!start && !end) return 'Por definir'
  const format = (value) => {
    if (!value) return ''
    const parsed = new Date(value)
    if (Number.isNaN(parsed.getTime())) return String(value)
    return parsed.toLocaleString('es-ES', {
      month: 'short',
      day: 'numeric',
      hour: '2-digit',
      minute: '2-digit',
    })
  }
  const left = format(start)
  const right = format(end)
  if (left && right) return `${left} → ${right}`
  return left || right
}

const countByStatus = (items = []) => {
  return items.reduce((acc, item) => {
    const code = item?.status?.code || 'unknown'
    acc[code] = (acc[code] || 0) + 1
    return acc
  }, {})
}

const buildTrend = (items = [], dateGetter, days = 7) => {
  const map = new Map()
  items.forEach((item) => {
    const key = dateGetter(item)
    if (!key) return
    map.set(key, (map.get(key) || 0) + 1)
  })

  const output = []
  const today = new Date()
  today.setHours(0, 0, 0, 0)

  for (let i = days - 1; i >= 0; i -= 1) {
    const current = new Date(today)
    current.setDate(today.getDate() - i)
    const key = current.toISOString().slice(0, 10)
    const label = current.toLocaleDateString('es-ES', { month: 'short', day: 'numeric' })
    output.push({ key, label, value: map.get(key) || 0 })
  }

  return output
}

const toDateKey = (value) => {
  if (!value) return null
  if (typeof value === 'string') return value.slice(0, 10)
  const parsed = new Date(value)
  if (Number.isNaN(parsed.getTime())) return null
  return parsed.toISOString().slice(0, 10)
}

export default function Dashboard() {
  const [summary, setSummary] = useState({
    tournaments: { total: 0, active: 0, draft: 0, completed: 0, categories: 0 },
    registrations: { total: 0, pending: 0, accepted: 0, waitlisted: 0, paid: 0 },
    payments: { total: 0, pending: 0, succeeded: 0, failed: 0 },
    matches: { today: 0, upcoming: 0, completed: 0 },
    leads: { total: 0, new: 0, contacted: 0, qualified: 0 },
  })
  const [activeTournament, setActiveTournament] = useState(null)
  const [categoryStats, setCategoryStats] = useState([])
  const [alerts, setAlerts] = useState([])
  const [trends, setTrends] = useState({ registrations: [], payments: [], leads: [] })
  const [loading, setLoading] = useState(true)
  const [error, setError] = useState('')
  const [lastUpdated, setLastUpdated] = useState(null)

  const load = async () => {
    setLoading(true)
    setError('')
    try {
      const [tournaments, registrations, payments, matches, leads] = await Promise.all([
        adminTournamentsApi.list(),
        adminRegistrationsApi.list(),
        adminPaymentsApi.list(),
        adminMatchesApi.list(),
        adminLeadsApi.list(),
      ])

      const today = new Date().toISOString().slice(0, 10)
      const matchesToday = (matches || []).filter((match) => {
        if (!match?.scheduled_at) return false
        return match.scheduled_at.slice(0, 10) === today
      }).length
      const matchesUpcoming = (matches || []).filter((match) => {
        if (!match?.scheduled_at) return false
        return match.scheduled_at.slice(0, 10) >= today
      }).length
      const matchStatus = countByStatus(matches || [])

      const paymentStatus = countByStatus(payments || [])
      const paymentsPending = Array.from(PAYMENT_PENDING_CODES).reduce(
        (sum, code) => sum + (paymentStatus[code] || 0),
        0,
      )
      const paymentsFailed = ['failed', 'cancelled'].reduce(
        (sum, code) => sum + (paymentStatus[code] || 0),
        0,
      )

      const registrationStatus = countByStatus(registrations || [])
      const tournamentStatus = countByStatus(tournaments || [])
      const leadStatus = countByStatus(leads || [])

      const activeTournaments = (tournaments || []).filter((tournament) =>
        ACTIVE_TOURNAMENT_CODES.has(tournament?.status?.code),
      )

      const sortedActive = [...activeTournaments].sort((a, b) => {
        const left = new Date(a.start_date || 0).getTime()
        const right = new Date(b.start_date || 0).getTime()
        return left - right
      })

      const selectedTournament = sortedActive[0] || (tournaments || [])[0] || null
      setActiveTournament(selectedTournament)

      const categoriesCount = (tournaments || []).reduce((sum, tournament) => {
        const count = tournament?.categories?.length || 0
        return sum + count
      }, 0)

      setSummary({
        tournaments: {
          total: tournaments?.length || 0,
          active: activeTournaments.length,
          draft: tournamentStatus?.draft || 0,
          completed: tournamentStatus?.completed || 0,
          categories: categoriesCount,
        },
        registrations: {
          total: registrations?.length || 0,
          pending: registrationStatus?.pending || 0,
          accepted: registrationStatus?.accepted || 0,
          waitlisted: registrationStatus?.waitlisted || 0,
          paid: registrationStatus?.paid || 0,
        },
        payments: {
          total: payments?.length || 0,
          pending: paymentsPending,
          succeeded: paymentStatus?.succeeded || 0,
          failed: paymentsFailed,
        },
        matches: {
          today: matchesToday,
          upcoming: matchesUpcoming,
          completed: matchStatus?.completed || 0,
        },
        leads: {
          total: leads?.length || 0,
          new: leadStatus?.new || 0,
          contacted: leadStatus?.contacted || 0,
          qualified: leadStatus?.qualified || 0,
        },
      })

      if (selectedTournament) {
        const tournamentCategories = selectedTournament?.categories || []
        const tournamentRegistrations = (registrations || []).filter((registration) => {
          const tournamentId = registration?.tournament_category?.tournament?.id
          return String(tournamentId) === String(selectedTournament.id)
        })

        const categorySummary = tournamentCategories.map((category) => {
          const categoryId = category?.id
          const categoryRegistrations = tournamentRegistrations.filter((registration) => {
            const regCategoryId = registration?.tournament_category?.id || registration?.tournament_category_id
            return String(regCategoryId) === String(categoryId)
          })

          const statusCounts = countByStatus(categoryRegistrations)
          const acceptedCount = Array.from(ACCEPTED_CODES).reduce(
            (sum, code) => sum + (statusCounts[code] || 0),
            0,
          )
          const waitlistCount = statusCounts?.waitlisted || 0
          const maxTeams = category?.max_teams || 0
          const occupancy = maxTeams ? Math.round((acceptedCount / maxTeams) * 100) : 0
          const rankingValues = categoryRegistrations
            .map((registration) => registration?.team_ranking_score)
            .filter((value) => Number.isFinite(value))
          const avgRanking = rankingValues.length
            ? Math.round(rankingValues.reduce((sum, value) => sum + value, 0) / rankingValues.length)
            : null

          return {
            id: categoryId,
            name: category?.category?.display_name || category?.category?.name || 'Categoría',
            maxTeams,
            total: categoryRegistrations.length,
            accepted: acceptedCount,
            waitlist: waitlistCount,
            occupancy,
            avgRanking,
          }
        })

        categorySummary.sort((a, b) => b.total - a.total)
        setCategoryStats(categorySummary)

        const now = new Date()
        const overduePayments = tournamentRegistrations.filter((registration) => {
          if (!registration?.payment_due_at) return false
          if (!['accepted', 'payment_pending'].includes(registration?.status?.code)) return false
          return new Date(registration.payment_due_at).getTime() < now.getTime()
        })

        const categoriesWithoutRegistrations = categorySummary.filter((item) => item.total === 0)
        const tournamentsWithoutCategories = (tournaments || []).filter((tournament) =>
          (tournament?.categories || []).length === 0,
        )

        const nextAlerts = []
        if (overduePayments.length > 0) {
          const overdueList = overduePayments.slice(0, 5).map((registration) => ({
            title: registration?.team?.display_name || 'Equipo',
            subtitle: `${registration?.tournament_category?.category?.display_name || 'Categoría'} · vence ${registration?.payment_due_at?.slice(0, 10) || ''}`,
          }))
          nextAlerts.push({
            title: 'Pagos vencidos',
            subtitle: `Torneo: ${selectedTournament?.name || 'Principal'}`,
            value: `${overduePayments.length} equipos`,
            tone: 'danger',
            items: overdueList,
          })
        }
        if (categoriesWithoutRegistrations.length > 0) {
          const categoriesList = categoriesWithoutRegistrations.slice(0, 5).map((category) => ({
            title: category.name,
            subtitle: `Cupo ${category.accepted}/${category.maxTeams || '—'}`,
          }))
          nextAlerts.push({
            title: 'Categorías sin inscripciones',
            subtitle: `Torneo: ${selectedTournament?.name || 'Principal'}`,
            value: `${categoriesWithoutRegistrations.length} categorías`,
            tone: 'warning',
            items: categoriesList,
          })
        }
        if (tournamentsWithoutCategories.length > 0) {
          const tournamentsList = tournamentsWithoutCategories.slice(0, 5).map((tournament) => ({
            title: tournament.name,
            subtitle: tournament.city || 'Sin ciudad',
          }))
          nextAlerts.push({
            title: 'Torneos sin categorías',
            subtitle: 'Requiere configuración',
            value: `${tournamentsWithoutCategories.length} torneos`,
            tone: 'warning',
            items: tournamentsList,
          })
        }
        if (nextAlerts.length === 0) {
          nextAlerts.push({
            title: 'Sin alertas críticas',
            subtitle: 'Todo en orden',
            value: 'OK',
            tone: 'success',
          })
        }
        setAlerts(nextAlerts)
      } else {
        setCategoryStats([])
        setAlerts([
          {
            title: 'No hay torneos activos',
            subtitle: 'Crea o publica un torneo',
            value: 'Revisar',
            tone: 'warning',
          },
        ])
      }

      const registrationTrend = buildTrend(
        registrations || [],
        (item) => toDateKey(item?.created_at),
      )
      const paymentTrend = buildTrend(
        (payments || []).filter((payment) => payment?.status?.code === 'succeeded'),
        (item) => toDateKey(item?.paid_at || item?.created_at),
      )
      const leadTrend = buildTrend(
        leads || [],
        (item) => toDateKey(item?.created_at),
      )

      setTrends({
        registrations: registrationTrend,
        payments: paymentTrend,
        leads: leadTrend,
      })

      setLastUpdated(new Date())
    } catch (err) {
      setError(err?.message || 'No pudimos cargar el resumen.')
    } finally {
      setLoading(false)
    }
  }

  useEffect(() => {
    load()
  }, [])

  const totalAccepted = categoryStats.reduce((sum, item) => sum + item.accepted, 0)
  const totalCapacity = categoryStats.reduce((sum, item) => sum + item.maxTeams, 0)
  const occupancy = totalCapacity ? Math.round((totalAccepted / totalCapacity) * 100) : 0

  return (
    <section className="admin-page">
      <div className="admin-page-header">
        <div>
          <h3>Dashboard</h3>
          <p>Visión ejecutiva con métricas en tiempo real.</p>
          {lastUpdated && <p>Actualizado: {lastUpdated.toLocaleString()}</p>}
        </div>
        <div className="admin-page-actions">
          <button className="secondary-button" type="button" onClick={load}>
            Actualizar
          </button>
        </div>
      </div>

      {loading ? (
        <div className="empty-state">Cargando indicadores...</div>
      ) : error ? (
        <div className="empty-state">{error}</div>
      ) : (
        <div className="dashboard-grid">
          <div className="dashboard-main">
            <div className="panel-card dashboard-hero">
              <div className="panel-header">
                <div>
                  <h4>Torneo activo</h4>
                  <p>Resumen operativo del torneo principal.</p>
                </div>
                <span className="tag">{activeTournament?.status?.label || 'Sin estado'}</span>
              </div>
              {activeTournament ? (
                <div className="dashboard-hero-grid">
                  <div className="dashboard-stat">
                    <span>Nombre</span>
                    <strong>{activeTournament.name}</strong>
                    <p>{activeTournament.city || 'Ciudad'} • {activeTournament.venue_name || 'Sede'}</p>
                  </div>
                  <div className="dashboard-stat">
                    <span>Fechas</span>
                    <strong>{formatDateRange(activeTournament.start_date, activeTournament.end_date)}</strong>
                    <p>Modo: {activeTournament.mode}</p>
                  </div>
                  <div className="dashboard-stat">
                    <span>Inscripciones</span>
                    <strong>
                      {formatDateTimeRange(
                        activeTournament.registration_open_at,
                        activeTournament.registration_close_at,
                      )}
                    </strong>
                    <p>Categorías: {activeTournament.categories?.length || 0}</p>
                  </div>
                  <div className="dashboard-stat">
                    <span>Cupo ocupado</span>
                    <strong>{occupancy}%</strong>
                    <p>Equipos aceptados</p>
                  </div>
                </div>
              ) : (
                <div className="empty-state">No hay torneos activos.</div>
              )}
            </div>

            <div className="panel-card">
              <div className="panel-header">
                <h4>KPIs principales</h4>
                <span className="tag muted">Actual</span>
              </div>
              <div className="kpi-grid">
                <div className="kpi-card">
                  <span>Equipos inscritos</span>
                  <strong>{summary.registrations.total}</strong>
                </div>
                <div className="kpi-card">
                  <span>Equipos aceptados</span>
                  <strong>{summary.registrations.accepted}</strong>
                </div>
                <div className="kpi-card">
                  <span>Waitlist</span>
                  <strong>{summary.registrations.waitlisted}</strong>
                </div>
                <div className="kpi-card">
                  <span>Pagos confirmados</span>
                  <strong>{summary.payments.succeeded}</strong>
                </div>
              </div>
            </div>

            <div className="panel-card">
              <div className="panel-header">
                <h4>Categorías principales</h4>
                <span className="tag muted">Top por inscripciones</span>
              </div>
              {categoryStats.length === 0 ? (
                <div className="empty-state">No hay categorías disponibles.</div>
              ) : (
                <div className="dashboard-category-grid">
                  {categoryStats.slice(0, 4).map((category) => (
                    <div key={category.id} className="dashboard-category-card">
                      <div className="category-row">
                        <strong>{category.name}</strong>
                        <span>{category.occupancy}%</span>
                      </div>
                      <div className="progress-bar">
                        <div className="progress-fill" style={{ width: `${Math.min(100, category.occupancy)}%` }} />
                      </div>
                      <div className="category-meta">
                        <span>Cupo: {category.accepted}/{category.maxTeams || '—'}</span>
                        <span>Waitlist: {category.waitlist}</span>
                      </div>
                      <div className="category-meta">
                        <span>Inscritos: {category.total}</span>
                        <span>Ranking promedio: {category.avgRanking ?? '—'}</span>
                      </div>
                    </div>
                  ))}
                </div>
              )}
            </div>

            <div className="panel-card">
              <div className="panel-header">
                <h4>Tendencias 7 días</h4>
                <span className="tag muted">Última semana</span>
              </div>
              <div className="trend-grid">
                <div className="trend-card">
                  <h5>Inscripciones</h5>
                  <div className="trend-list">
                    {trends.registrations.map((item) => (
                      <div key={item.key} className="trend-item">
                        <span>{item.label}</span>
                        <strong>{item.value}</strong>
                      </div>
                    ))}
                  </div>
                </div>
                <div className="trend-card">
                  <h5>Pagos confirmados</h5>
                  <div className="trend-list">
                    {trends.payments.map((item) => (
                      <div key={item.key} className="trend-item">
                        <span>{item.label}</span>
                        <strong>{item.value}</strong>
                      </div>
                    ))}
                  </div>
                </div>
                <div className="trend-card">
                  <h5>Leads</h5>
                  <div className="trend-list">
                    {trends.leads.map((item) => (
                      <div key={item.key} className="trend-item">
                        <span>{item.label}</span>
                        <strong>{item.value}</strong>
                      </div>
                    ))}
                  </div>
                </div>
              </div>
            </div>
          </div>

          <div className="dashboard-side">
            <div className="panel-card">
              <div className="panel-header">
                <h4>Alertas</h4>
                <span className="tag muted">Acciones rápidas</span>
              </div>
              <div className="alert-grid">
                {alerts.map((alert, index) => (
                  <div key={`${alert.title}-${index}`} className={`alert-card ${alert.tone || ''}`}>
                    <span>{alert.title}</span>
                    <strong>{alert.value}</strong>
                    {alert.subtitle && <em>{alert.subtitle}</em>}
                    {alert.items && alert.items.length > 0 && (
                      <div className="alert-items">
                        {alert.items.map((item, itemIndex) => (
                          <div key={`${item.title}-${itemIndex}`} className="alert-item">
                            <strong>{item.title}</strong>
                            {item.subtitle && <span>{item.subtitle}</span>}
                          </div>
                        ))}
                      </div>
                    )}
                  </div>
                ))}
              </div>
            </div>

            <div className="panel-card">
              <div className="panel-header">
                <h4>Operación hoy</h4>
                <span className="tag muted">Snapshot</span>
              </div>
              <div className="stat-list">
                <div className="stat-row">
                  <span>Partidos hoy</span>
                  <strong>{summary.matches.today}</strong>
                </div>
                <div className="stat-row">
                  <span>Partidos programados</span>
                  <strong>{summary.matches.upcoming}</strong>
                </div>
                <div className="stat-row">
                  <span>Pagos pendientes</span>
                  <strong>{summary.payments.pending}</strong>
                </div>
                <div className="stat-row">
                  <span>Pagos fallidos</span>
                  <strong>{summary.payments.failed}</strong>
                </div>
                <div className="stat-row">
                  <span>Leads nuevos</span>
                  <strong>{summary.leads.new}</strong>
                </div>
                <div className="stat-row">
                  <span>Leads contactados</span>
                  <strong>{summary.leads.contacted}</strong>
                </div>
                <div className="stat-row">
                  <span>Torneos activos</span>
                  <strong>{summary.tournaments.active}</strong>
                </div>
                <div className="stat-row">
                  <span>Torneos finalizados</span>
                  <strong>{summary.tournaments.completed}</strong>
                </div>
              </div>
            </div>
          </div>
        </div>
      )}
    </section>
  )
}
