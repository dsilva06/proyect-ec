import { useMemo } from 'react'

const formatDateTime = (value) => {
  if (!value) return ''
  const parsed = new Date(value)
  if (Number.isNaN(parsed.getTime())) return String(value)
  return parsed.toLocaleString('en-US', {
    month: 'short',
    day: 'numeric',
    hour: '2-digit',
    minute: '2-digit',
    hour12: false,
  })
}

const getDrawSize = (bracket) => {
  if (bracket.draw_size) return bracket.draw_size
  const slots = bracket.slots || []
  if (slots.length) return slots.length
  const matches = bracket.matches || []
  const roundOne = matches.filter((match) => Number(match.round_number) === 1)
  if (roundOne.length) return roundOne.length * 2
  return 0
}

const buildRoundsFromMatches = (matches, roundsCount) => {
  if (!matches.length) return []
  const byRound = Array.from({ length: roundsCount }, () => [])
  matches
    .slice()
    .sort((a, b) => Number(a.round_number) - Number(b.round_number) || Number(a.match_number) - Number(b.match_number))
    .forEach((match) => {
      const index = (Number(match.round_number) || 1) - 1
      if (!byRound[index]) byRound[index] = []
      byRound[index].push(match)
    })
  return byRound
}

const buildRoundsFromSlots = (slots, roundsCount) => {
  if (!slots.length) return []
  const roundOne = []
  for (let i = 0; i < slots.length; i += 2) {
    roundOne.push({
      round_number: 1,
      match_number: i / 2 + 1,
      registration_a: slots[i]?.registration,
      registration_b: slots[i + 1]?.registration,
    })
  }

  const rounds = [roundOne]
  for (let round = 2; round <= roundsCount; round += 1) {
    const matches = slots.length / 2 ** round
    rounds.push(
      Array.from({ length: matches }, (_, idx) => ({
        round_number: round,
        match_number: idx + 1,
      })),
    )
  }
  return rounds
}

const getRegistration = (match, side) => {
  if (side === 'a') {
    return match.registration_a || match.registrationA
  }
  return match.registration_b || match.registrationB
}

const getTeamLabel = (registration, other) => {
  if (registration?.team) {
    return registration.team.display_name || 'Equipo'
  }
  if (other?.team) {
    return 'BYE'
  }
  return '—'
}

export default function BracketView({ bracket, onMatchClick }) {
  const rounds = useMemo(() => {
    const drawSize = getDrawSize(bracket)
    if (!drawSize) return []
    const roundsCount = Math.max(1, Math.round(Math.log2(drawSize)))
    const matches = bracket.matches || []
    if (matches.length) {
      return buildRoundsFromMatches(matches, roundsCount)
    }
    return buildRoundsFromSlots(bracket.slots || [], roundsCount)
  }, [bracket])

  if (!rounds.length) return <p className="muted">Sin cuadro disponible.</p>

  return (
    <div className="bracket-board bracket-tree">
      {rounds.map((matches, roundIndex) => {
        const gap = 18 * 2 ** roundIndex
        const offset = roundIndex === 0 ? 0 : gap / 2
        const isFinal = roundIndex === rounds.length - 1
        return (
          <div
            key={`round-${roundIndex}`}
            className="bracket-round"
            style={{ gap: `${gap}px`, paddingTop: `${offset}px` }}
          >
            <div className="bracket-round-header">Ronda {roundIndex + 1}</div>
            {matches.map((match) => {
              const registrationA = getRegistration(match, 'a')
              const registrationB = getRegistration(match, 'b')
              const winnerId = match.winner_registration?.id || match.winnerRegistration?.id
              const matchTime = match.not_before_at || match.scheduled_at
              const hasClick = typeof onMatchClick === 'function' && match.id
              return (
                <div
                  key={`match-${roundIndex}-${match.match_number}`}
                  className={`bracket-match ${isFinal ? 'is-final' : ''} ${hasClick ? 'is-clickable' : ''}`}
                  onClick={() => {
                    if (hasClick) onMatchClick(match)
                  }}
                  role={hasClick ? 'button' : undefined}
                  tabIndex={hasClick ? 0 : undefined}
                  onKeyDown={(event) => {
                    if (hasClick && event.key === 'Enter') onMatchClick(match)
                  }}
                >
                  <div
                    className={`bracket-team ${winnerId && winnerId === registrationA?.id ? 'is-winner' : ''} ${
                      !registrationA?.team && registrationB?.team ? 'is-bye' : ''
                    }`}
                  >
                    {registrationA?.seed_number ? <span className="seed-badge">#{registrationA.seed_number}</span> : null}
                    <span className="team-name">{getTeamLabel(registrationA, registrationB)}</span>
                  </div>
                  <div
                    className={`bracket-team ${winnerId && winnerId === registrationB?.id ? 'is-winner' : ''} ${
                      !registrationB?.team && registrationA?.team ? 'is-bye' : ''
                    }`}
                  >
                    {registrationB?.seed_number ? <span className="seed-badge">#{registrationB.seed_number}</span> : null}
                    <span className="team-name">{getTeamLabel(registrationB, registrationA)}</span>
                  </div>
                  {matchTime || match.court ? (
                    <div className="bracket-match-meta">
                      {matchTime ? <span>{formatDateTime(matchTime)}</span> : null}
                      {match.court ? <span>Cancha {match.court}</span> : null}
                    </div>
                  ) : null}
                </div>
              )
            })}
          </div>
        )
      })}
    </div>
  )
}
