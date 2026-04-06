import { useEffect, useMemo, useState } from 'react'
import { Link } from 'react-router-dom'
import { playerTeamInvitesApi } from '../../features/teamInvites/api'
import { playerRegistrationsApi } from '../../features/registrations/api'
import { playerTournamentsApi } from '../../features/tournaments/api'
import { formatPlayerDate, getPlayerStatusTone } from './ui'

const getHeadline = ({ pendingInvites, pendingPayments, tournaments }) => {
  if (pendingInvites > 0) return 'Tienes una invitación pendiente por responder.'
  if (pendingPayments > 0) return 'Hay una inscripción esperando tu pago.'
  if (tournaments > 0) return 'Ya puedes revisar torneos y decidir tu próxima inscripción.'
  return 'Tu torneo estará listo aquí cuando abramos nuevas fechas.'
}

export default function Dashboard() {
  const [tournaments, setTournaments] = useState([])
  const [registrations, setRegistrations] = useState([])
  const [invites, setInvites] = useState([])
  const [error, setError] = useState('')

  const load = async () => {
    try {
      setError('')
      const [tournamentsData, registrationsData, invitesData] = await Promise.all([
        playerTournamentsApi.list(),
        playerRegistrationsApi.list(),
        playerTeamInvitesApi.list(),
      ])
      setTournaments(tournamentsData)
      setRegistrations(registrationsData)
      setInvites(invitesData)
    } catch (err) {
      setError(err?.message || 'No pudimos cargar tu resumen de torneo.')
    }
  }

  useEffect(() => {
    load()
  }, [])

  const pendingInvites = useMemo(
    () => invites.filter((invite) => String(invite.status?.code || '').toLowerCase() === 'sent'),
    [invites],
  )
  const pendingPayments = useMemo(
    () => registrations.filter((registration) => String(registration.status?.code || '').toLowerCase() === 'payment_pending'),
    [registrations],
  )
  const confirmedRegistrations = useMemo(
    () => registrations.filter((registration) => ['accepted', 'paid'].includes(String(registration.status?.code || '').toLowerCase())),
    [registrations],
  )
  const recentRegistrations = registrations.slice(0, 3)
  const nextTournament = tournaments[0] || null

  return (
    <section className="player-page player-dashboard-page">
      <div className="player-page-header">
        <div>
          <span className="player-section-kicker">Resumen de torneo</span>
          <h3>Todo lo importante, claro y rápido en tu teléfono.</h3>
          <p>Revisa tus pendientes, responde invitaciones y entra a cada torneo sin perderte en pantallas de escritorio.</p>
        </div>
        <div className="player-page-actions">
          <button className="secondary-button" type="button" onClick={load}>
            Actualizar
          </button>
        </div>
      </div>

      {error && <div className="empty-state">{error}</div>}

      <section className="player-hero-card">
        <div className="player-hero-copy">
          <span className="player-section-kicker">Panorama del día</span>
          <h4>{getHeadline({ pendingInvites: pendingInvites.length, pendingPayments: pendingPayments.length, tournaments: tournaments.length })}</h4>
          <p>
            {pendingInvites.length > 0
              ? 'Acepta la invitación para quedar confirmado con tu pareja y no perder la inscripción.'
              : 'Desde aquí puedes entrar a torneos, revisar tu estatus y saber exactamente qué te falta hacer.'}
          </p>
        </div>
        <div className="player-cta-row">
          <Link className="primary-button" to={pendingInvites.length > 0 ? '/player/invitations' : '/player/tournaments'}>
            {pendingInvites.length > 0 ? 'Ver invitaciones' : 'Ver torneos'}
          </Link>
          <Link className="ghost-button" to="/player/registrations">
            Ver mis inscripciones
          </Link>
        </div>
      </section>

      <div className="player-stat-grid">
        <article className="player-stat-card">
          <span>Torneos abiertos</span>
          <strong>{tournaments.length}</strong>
          <small>Opciones disponibles para inscribirte.</small>
        </article>
        <article className="player-stat-card">
          <span>Invitaciones pendientes</span>
          <strong>{pendingInvites.length}</strong>
          <small>Equipos esperando tu confirmación.</small>
        </article>
        <article className="player-stat-card">
          <span>Pagos por resolver</span>
          <strong>{pendingPayments.length}</strong>
          <small>Inscripciones con siguiente paso financiero.</small>
        </article>
        <article className="player-stat-card">
          <span>Inscripciones firmes</span>
          <strong>{confirmedRegistrations.length}</strong>
          <small>Registros ya encaminados o cerrados.</small>
        </article>
      </div>

      <div className="player-section-grid">
        <section className="panel-card player-surface-card">
          <div className="panel-header">
            <h4>Próximo torneo para mirar</h4>
            <Link className="ghost-button" to="/player/tournaments">Abrir torneos</Link>
          </div>
          {nextTournament ? (
            <article className="player-info-card">
              <div className="player-card-topline">
                <span className={`player-status-pill tone-${getPlayerStatusTone(nextTournament.status?.code)}`}>
                  {nextTournament.status?.label || 'Publicado'}
                </span>
                <span className="player-soft-note">{nextTournament.city || 'Ciudad por confirmar'}</span>
              </div>
              <h5>{nextTournament.name}</h5>
              <p>{nextTournament.venue_name || 'Sede por confirmar'}</p>
              <div className="player-metadata-grid">
                <div>
                  <span>Fechas</span>
                  <strong>{formatPlayerDate(nextTournament.start_date)} - {formatPlayerDate(nextTournament.end_date)}</strong>
                </div>
                <div>
                  <span>Categorías</span>
                  <strong>{nextTournament.categories?.length || 0}</strong>
                </div>
              </div>
            </article>
          ) : (
            <div className="player-empty-state">Aún no hay torneos visibles para mostrar aquí.</div>
          )}
        </section>

        <section className="panel-card player-surface-card">
          <div className="panel-header">
            <h4>Tu actividad reciente</h4>
            <Link className="ghost-button" to="/player/registrations">Ir al detalle</Link>
          </div>
          {recentRegistrations.length === 0 ? (
            <div className="player-empty-state">Todavía no tienes actividad de inscripción registrada.</div>
          ) : (
            <div className="player-card-stack">
              {recentRegistrations.map((registration) => (
                <article key={registration.id} className="player-info-card compact">
                  <div className="player-card-topline">
                    <span className={`player-status-pill tone-${getPlayerStatusTone(registration.status?.code)}`}>
                      {registration.status?.label || 'Sin estado'}
                    </span>
                    <span className="player-soft-note">{formatPlayerDate(registration.created_at, { year: 'numeric' })}</span>
                  </div>
                  <h5>{registration.team?.display_name || 'Equipo'}</h5>
                  <p>{registration.tournament_category?.tournament?.name || 'Torneo'}</p>
                  <div className="player-metadata-grid">
                    <div>
                      <span>Categoría</span>
                      <strong>{registration.tournament_category?.category?.display_name || registration.tournament_category?.category?.name || 'Por confirmar'}</strong>
                    </div>
                    <div>
                      <span>Cola</span>
                      <strong>{registration.queue_position ?? '—'}</strong>
                    </div>
                  </div>
                </article>
              ))}
            </div>
          )}
        </section>
      </div>
    </section>
  )
}
