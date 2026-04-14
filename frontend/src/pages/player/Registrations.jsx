import { useEffect, useMemo, useState } from 'react'
import { useLocation } from 'react-router-dom'
import { useAuth } from '../../auth/useAuth'
import { playerOpenEntriesApi } from '../../features/openEntries/api'
import { playerRegistrationsApi } from '../../features/registrations/api'
import { formatPlayerDate, getPlayerStatusTone, getPlayerRegistrationStageLabel } from './ui'

const SEGMENT_LABELS = {
  men: 'Masculino',
  women: 'Femenino',
}

const OPEN_ASSIGNMENT_LABELS = {
  pending: 'Pendiente de asignacion',
  assigned: 'Categoria asignada',
}

const getOpenEntryStageLabel = (entry) => {
  const isPaid = Boolean(entry?.paid_at || entry?.payment_is_covered)
  const isAssigned = entry?.assignment_status === 'assigned'

  if (isAssigned) return 'Inscripcion definitiva creada'
  if (isPaid) return 'Pagado, esperando asignacion de categoria'
  return 'Enviada, pendiente de pago'
}

const getOpenEntryTone = (entry) => {
  const isPaid = Boolean(entry?.paid_at || entry?.payment_is_covered)
  const isAssigned = entry?.assignment_status === 'assigned'

  if (isAssigned) return 'success'
  if (isPaid) return 'warning'
  return 'neutral'
}

export default function PlayerRegistrations() {
  const location = useLocation()
  const { user } = useAuth()
  const [registrations, setRegistrations] = useState([])
  const [openEntries, setOpenEntries] = useState([])
  const [error, setError] = useState('')
  const [payingId, setPayingId] = useState(null)
  const [payingOpenId, setPayingOpenId] = useState(null)
  const [checkoutMessage, setCheckoutMessage] = useState('')

  const load = async () => {
    try {
      setError('')
      const [registrationsData, openEntriesData] = await Promise.all([
        playerRegistrationsApi.list(),
        playerOpenEntriesApi.list(),
      ])
      setRegistrations(registrationsData)
      setOpenEntries(openEntriesData)
    } catch (err) {
      setError(err?.message || 'No pudimos cargar tus inscripciones.')
    }
  }

  useEffect(() => {
    load()
  }, [])

  useEffect(() => {
    const params = new URLSearchParams(location.search)
    const checkout = params.get('checkout')

    if (checkout === 'success') {
      setCheckoutMessage('Volviste desde Stripe. Estamos confirmando el pago del equipo.')
      load()
      return
    }

    if (checkout === 'cancelled') {
      setCheckoutMessage(
        'El pago fue cancelado antes de completarse. Puedes intentarlo nuevamente.',
      )
      load()
      return
    }

    setCheckoutMessage('')
  }, [location.search])

  const handlePay = async (registrationId) => {
    setPayingId(registrationId)
    setError('')
    try {
      const checkout = await playerRegistrationsApi.pay(registrationId)
      if (checkout?.checkout_url) {
        window.location.assign(checkout.checkout_url)
        return
      }
      throw new Error('No pudimos abrir Stripe Checkout.')
    } catch (err) {
      setError(err?.data?.message || err?.message || 'No pudimos registrar el pago del equipo.')
    } finally {
      setPayingId(null)
    }
  }

  const handlePayOpenEntry = async (entryId) => {
    setPayingOpenId(entryId)
    setError('')
    try {
      const checkout = await playerOpenEntriesApi.pay(entryId)
      if (checkout?.checkout_url) {
        window.location.assign(checkout.checkout_url)
        return
      }
      throw new Error('No pudimos abrir Stripe Checkout.')
    } catch (err) {
      setError(
        err?.data?.message || err?.message || 'No pudimos registrar el pago de la entrada OPEN.',
      )
    } finally {
      setPayingOpenId(null)
    }
  }

  const pendingItems = useMemo(
    () =>
      registrations.filter((registration) =>
        ['pending', 'accepted', 'payment_pending', 'waitlisted'].includes(
          String(registration.status?.code || '').toLowerCase(),
        ),
      ),
    [registrations],
  )

  const confirmedItems = useMemo(
    () =>
      registrations.filter((registration) =>
        ['awaiting_partner_acceptance', 'paid'].includes(
          String(registration.status?.code || '').toLowerCase(),
        ),
      ),
    [registrations],
  )

  const openEntriesPendingPayment = useMemo(
    () => openEntries.filter((entry) => !entry.paid_at && !entry.payment_is_covered),
    [openEntries],
  )

  const openEntriesPaid = useMemo(
    () => openEntries.filter((entry) => entry.paid_at || entry.payment_is_covered),
    [openEntries],
  )

  const totalParticipations = registrations.length + openEntries.length

  return (
    <section className="player-page">
      <div className="player-page-header">
        <div>
          <span className="player-section-kicker">Inscripciones</span>
          <h3>Entiende tu estado de torneo de un vistazo.</h3>
          <p>Inscripciones regulares y entradas OPEN en el mismo lugar.</p>
        </div>
        <div className="player-page-actions">
          <button className="secondary-button" type="button" onClick={load}>
            Actualizar
          </button>
        </div>
      </div>

      {error && <div className="empty-state">{error}</div>}
      {checkoutMessage && !error ? <div className="empty-state">{checkoutMessage}</div> : null}

      <div className="player-stat-grid">
        <article className="player-stat-card">
          <span>Total participaciones</span>
          <strong>{totalParticipations}</strong>
          <small>Inscripciones y entradas OPEN combinadas.</small>
        </article>
        <article className="player-stat-card">
          <span>Inscripciones pendientes</span>
          <strong>{pendingItems.length}</strong>
          <small>Necesitan seguimiento o pago.</small>
        </article>
        <article className="player-stat-card">
          <span>Inscripciones confirmadas</span>
          <strong>{confirmedItems.length}</strong>
          <small>Pagadas o en proceso de confirmacion.</small>
        </article>
        {openEntries.length > 0 && (
          <article className="player-stat-card">
            <span>Entradas OPEN</span>
            <strong>{openEntries.length}</strong>
            <small>
              {openEntriesPaid.length} pagadas · {openEntriesPendingPayment.length} sin pagar.
            </small>
          </article>
        )}
      </div>

      {registrations.length > 0 && (
        <>
          <div className="player-section-heading">
            <h4>Inscripciones de equipo</h4>
          </div>
          <div className="player-card-stack">
            {registrations.map((registration) => (
              <article key={registration.id} className="player-info-card">
                <div className="player-card-topline">
                  <span
                    className={`player-status-pill tone-${getPlayerStatusTone(
                      registration.status?.code,
                    )}`}
                  >
                    {registration.status?.label || 'Sin estado'}
                  </span>
                  <span className="player-soft-note">
                    {formatPlayerDate(registration.created_at, { year: 'numeric' })}
                  </span>
                </div>
                <h5>{registration.team?.display_name || 'Equipo'}</h5>
                <p>
                  {registration.tournament_category?.tournament?.name || 'Torneo'}
                  {' · '}
                  {registration.tournament_category?.category?.display_name ||
                    registration.tournament_category?.category?.name ||
                    'Categoria'}
                </p>
                <div className="player-metadata-grid">
                  <div>
                    <span>Etapa</span>
                    <strong>{getPlayerRegistrationStageLabel(registration.status?.code)}</strong>
                  </div>
                  <div>
                    <span>Ranking equipo</span>
                    <strong>{registration.team_ranking_score ?? 'Pendiente'}</strong>
                  </div>
                  <div>
                    <span>Posicion en cola</span>
                    <strong>{registration.queue_position ?? '—'}</strong>
                  </div>
                  <div>
                    <span>Tipo</span>
                    <strong>{registration.is_wildcard ? 'Wildcard' : 'Regular'}</strong>
                  </div>
                </div>
                {registration.team?.created_by === user?.id &&
                ['accepted', 'payment_pending'].includes(
                  String(registration.status?.code || '').toLowerCase(),
                ) ? (
                  <div className="player-cta-row">
                    <button
                      className="primary-button"
                      type="button"
                      onClick={() => handlePay(registration.id)}
                      disabled={payingId === registration.id}
                    >
                      {payingId === registration.id
                        ? 'Registrando pago...'
                        : 'Pagar e invitar pareja'}
                    </button>
                  </div>
                  ) : null}
              </article>
            ))}
          </div>
        </>
      )}

      {openEntries.length > 0 && (
        <>
          <div className="player-section-heading">
            <h4>Entradas OPEN</h4>
            <p className="muted">Tu categoria sera asignada por el arbitro despues del pago.</p>
          </div>
          <div className="player-card-stack">
            {openEntries.map((entry) => {
              const isPaid = Boolean(entry.paid_at || entry.payment_is_covered)
              const isAssigned = entry.assignment_status === 'assigned'
              const partnerName =
                [entry.partner_first_name, entry.partner_last_name].filter(Boolean).join(' ') ||
                entry.partner_email

              return (
                <article key={entry.id} className="player-info-card">
                  <div className="player-card-topline">
                    <span className={`player-status-pill tone-${getOpenEntryTone(entry)}`}>
                      {OPEN_ASSIGNMENT_LABELS[entry.assignment_status] || entry.assignment_status}
                    </span>
                    <span className="tag accent">OPEN</span>
                    <span className="player-soft-note">
                      {formatPlayerDate(entry.created_at, { year: 'numeric' })}
                    </span>
                  </div>
                  <h5>{entry.team?.display_name || `${user?.name || 'Tu'} / ${partnerName}`}</h5>
                  <p>{entry.tournament?.name || 'Torneo'}</p>
                  <div className="player-metadata-grid">
                    <div>
                      <span>Etapa</span>
                      <strong>{getOpenEntryStageLabel(entry)}</strong>
                    </div>
                    <div>
                      <span>Segmento</span>
                      <strong>{SEGMENT_LABELS[entry.segment] || entry.segment || '—'}</strong>
                    </div>
                    <div>
                      <span>Pareja</span>
                      <strong>{partnerName}</strong>
                    </div>
                    {isAssigned && (
                      <div>
                        <span>Categoria asignada</span>
                        <strong>
                          {entry.assigned_tournament_category?.category?.display_name ||
                            entry.assigned_tournament_category?.category?.name ||
                            '—'}
                        </strong>
                      </div>
                    )}
                  </div>
                  {!isPaid ? (
                    <div className="player-cta-row">
                      <button
                        className="primary-button"
                        type="button"
                        onClick={() => handlePayOpenEntry(entry.id)}
                        disabled={payingOpenId === entry.id}
                      >
                        {payingOpenId === entry.id
                          ? 'Registrando pago...'
                          : 'Pagar entrada OPEN'}
                      </button>
                    </div>
                  ) : null}
                </article>
              )
            })}
          </div>
        </>
      )}

      {totalParticipations === 0 && (
        <div className="player-empty-state">Aun no tienes inscripciones registradas.</div>
      )}
    </section>
  )
}
