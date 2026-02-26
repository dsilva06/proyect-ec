import { useMemo } from 'react'

const CARD_WIDTH = 270
const CARD_HEIGHT = 96
const CARD_GAP_Y = 18
const ROUND_GAP_X = 86
const CANVAS_PADDING_X = 18
const CANVAS_PADDING_TOP = 44
const CANVAS_PADDING_BOTTOM = 30

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
  if (bracket.draw_size) return Number(bracket.draw_size)
  const slots = bracket.slots || []
  if (slots.length) return slots.length
  const matches = bracket.matches || []
  const roundOne = matches.filter((match) => Number(match.round_number) === 1)
  if (roundOne.length) return roundOne.length * 2
  return 0
}

const getRoundsCount = (matches, drawSize) => {
  const roundsFromMatches = matches.length
    ? Math.max(...matches.map((match) => Number(match.round_number) || 1))
    : 0
  const roundsFromSize = drawSize > 1 ? Math.ceil(Math.log2(drawSize)) : 1
  return Math.max(1, roundsFromMatches, roundsFromSize)
}

const getRoundLabel = (roundIndex, roundsCount) => {
  const fromEnd = roundsCount - roundIndex
  if (fromEnd === 1) return 'Final'
  if (fromEnd === 2) return 'Semifinal'
  if (fromEnd === 3) return 'Cuartos'
  if (fromEnd === 4) return 'Octavos'
  if (fromEnd === 5) return 'Dieciseisavos'
  return `Ronda ${roundIndex + 1}`
}

const buildRoundsFromMatches = (matches, roundsCount, drawSize) => {
  if (!drawSize) return []

  const byRound = Array.from({ length: roundsCount }, (_, roundIndex) => {
    const inRound = matches.filter((item) => Number(item.round_number) === roundIndex + 1)
    const maxMatchNumber = inRound.length
      ? Math.max(...inRound.map((item) => Number(item.match_number) || 1))
      : 0
    const expectedMatches = Math.max(1, Math.ceil(drawSize / 2 ** (roundIndex + 1)))
    const totalMatches = Math.max(maxMatchNumber, expectedMatches)

    return Array.from({ length: totalMatches }, (_, idx) => ({
      round_number: roundIndex + 1,
      match_number: idx + 1,
      is_placeholder: true,
    }))
  })

  matches
    .slice()
    .sort((a, b) => Number(a.round_number) - Number(b.round_number) || Number(a.match_number) - Number(b.match_number))
    .forEach((match) => {
      const roundIndex = (Number(match.round_number) || 1) - 1
      const matchIndex = (Number(match.match_number) || 1) - 1
      if (!byRound[roundIndex]) return
      byRound[roundIndex][matchIndex] = {
        ...byRound[roundIndex][matchIndex],
        ...match,
        is_placeholder: false,
      }
    })

  return byRound
}

const buildRoundsFromSlots = (slots, roundsCount) => {
  if (!slots.length) return []
  const orderedSlots = slots.slice().sort((a, b) => Number(a.slot_number) - Number(b.slot_number))

  const roundOne = []
  for (let i = 0; i < orderedSlots.length; i += 2) {
    roundOne.push({
      round_number: 1,
      match_number: i / 2 + 1,
      registration_a: orderedSlots[i]?.registration,
      registration_b: orderedSlots[i + 1]?.registration,
      is_placeholder: false,
    })
  }

  const rounds = [roundOne]
  for (let round = 2; round <= roundsCount; round += 1) {
    const matches = Math.max(1, orderedSlots.length / 2 ** round)
    rounds.push(
      Array.from({ length: matches }, (_, idx) => ({
        round_number: round,
        match_number: idx + 1,
        is_placeholder: true,
      })),
    )
  }

  return rounds
}

const getRegistration = (match, side) => {
  if (side === 'a') return match.registration_a || match.registrationA
  return match.registration_b || match.registrationB
}

const getTeamLabel = (registration, other, isPlaceholder = false) => {
  if (registration?.team) return registration.team.display_name || 'Equipo'
  if (other?.team) return 'BYE'
  return isPlaceholder ? 'Pendiente' : '—'
}

const computeLayout = (rounds) => {
  if (!rounds.length) {
    return {
      positionsByRound: [],
      width: 0,
      height: 0,
      xStep: CARD_WIDTH + ROUND_GAP_X,
    }
  }

  const positionsByRound = []

  positionsByRound[0] = rounds[0].map((_, index) =>
    CANVAS_PADDING_TOP + index * (CARD_HEIGHT + CARD_GAP_Y),
  )

  for (let roundIndex = 1; roundIndex < rounds.length; roundIndex += 1) {
    const previousRound = positionsByRound[roundIndex - 1]
    positionsByRound[roundIndex] = rounds[roundIndex].map((_, matchIndex) => {
      const fallback = previousRound[Math.min(matchIndex, Math.max(0, previousRound.length - 1))]
        ?? CANVAS_PADDING_TOP
      const a = previousRound[matchIndex * 2]
      const b = previousRound[matchIndex * 2 + 1]
      const safeA = Number.isFinite(a) ? a : fallback
      const safeB = Number.isFinite(b) ? b : safeA
      return (safeA + safeB) / 2
    })
  }

  const xStep = CARD_WIDTH + ROUND_GAP_X
  const width = CANVAS_PADDING_X * 2 + CARD_WIDTH + xStep * Math.max(0, rounds.length - 1)

  const firstRound = positionsByRound[0]
  const lowestY = firstRound.length
    ? firstRound[firstRound.length - 1]
    : CANVAS_PADDING_TOP
  const height = lowestY + CARD_HEIGHT + CANVAS_PADDING_BOTTOM

  return {
    positionsByRound,
    width,
    height,
    xStep,
  }
}

export default function BracketView({ bracket, onMatchClick, matchActionLabel = '' }) {
  const { rounds, roundsCount } = useMemo(() => {
    const drawSize = getDrawSize(bracket)
    if (!drawSize) return { rounds: [], roundsCount: 0 }

    const matches = bracket.matches || []
    const totalRounds = getRoundsCount(matches, drawSize)

    if (matches.length) {
      return {
        rounds: buildRoundsFromMatches(matches, totalRounds, drawSize),
        roundsCount: totalRounds,
      }
    }

    return {
      rounds: buildRoundsFromSlots(bracket.slots || [], totalRounds),
      roundsCount: totalRounds,
    }
  }, [bracket])

  const layout = useMemo(() => computeLayout(rounds), [rounds])

  if (!rounds.length) return <p className="muted">Sin cuadro disponible.</p>

  return (
    <div className="bracket-stage">
      <div className="bracket-canvas" style={{ width: `${layout.width}px`, height: `${layout.height}px` }}>
        <svg className="bracket-lines" width={layout.width} height={layout.height} aria-hidden="true">
          {rounds.slice(1).map((matches, roundOffset) => {
            const roundIndex = roundOffset + 1
            const previousX = CANVAS_PADDING_X + (roundIndex - 1) * layout.xStep + CARD_WIDTH
            const currentX = CANVAS_PADDING_X + roundIndex * layout.xStep
            const middleX = previousX + (currentX - previousX) / 2

            return matches.map((_, matchIndex) => {
              const prevRoundY = layout.positionsByRound[roundIndex - 1] || []
              const currentRoundY = layout.positionsByRound[roundIndex] || []

              const fallbackY = currentRoundY[matchIndex] ?? prevRoundY[prevRoundY.length - 1] ?? CANVAS_PADDING_TOP
              const roundA = prevRoundY[matchIndex * 2]
              const roundB = prevRoundY[matchIndex * 2 + 1]
              const yA = (Number.isFinite(roundA) ? roundA : fallbackY) + CARD_HEIGHT / 2
              const yB = (Number.isFinite(roundB) ? roundB : (Number.isFinite(roundA) ? roundA : fallbackY)) + CARD_HEIGHT / 2
              const yTop = Math.min(yA, yB)
              const yBottom = Math.max(yA, yB)
              const yCurrent = (currentRoundY[matchIndex] ?? fallbackY) + CARD_HEIGHT / 2

              return (
                <g key={`line-${roundIndex}-${matchIndex}`} className="bracket-line-group">
                  <path d={`M ${previousX} ${yA} H ${middleX}`} />
                  <path d={`M ${previousX} ${yB} H ${middleX}`} />
                  <path d={`M ${middleX} ${yTop} V ${yBottom}`} />
                  <path d={`M ${middleX} ${yCurrent} H ${currentX}`} />
                </g>
              )
            })
          })}
        </svg>

        {rounds.map((matches, roundIndex) => {
          const x = CANVAS_PADDING_X + roundIndex * layout.xStep
          const label = getRoundLabel(roundIndex, roundsCount)

          return (
            <div key={`round-${roundIndex}`} className="bracket-round-layer">
              <div className="bracket-round-label" style={{ left: `${x}px` }}>
                {label}
              </div>

              {matches.map((match, matchIndex) => {
                const y = layout.positionsByRound[roundIndex]?.[matchIndex] ?? CANVAS_PADDING_TOP
                const registrationA = getRegistration(match, 'a')
                const registrationB = getRegistration(match, 'b')
                const winnerId = match.winner_registration?.id || match.winnerRegistration?.id
                const matchTime = match.not_before_at || match.scheduled_at
                const hasClick = typeof onMatchClick === 'function'
                const isPlaceholder = Boolean(match.is_placeholder && !match.id)
                const canOpenMatch = hasClick && !isPlaceholder && Boolean(match.id)
                const showActionButton = canOpenMatch && Boolean(matchActionLabel)
                const statusLabel = match.status?.label || (isPlaceholder ? 'Pendiente' : 'Programado')

                return (
                  <div
                    key={`match-${roundIndex}-${match.match_number}`}
                    className={`bracket-node ${canOpenMatch ? 'is-clickable' : ''} ${isPlaceholder ? 'is-placeholder' : ''}`}
                    style={{ left: `${x}px`, top: `${y}px`, width: `${CARD_WIDTH}px`, height: `${CARD_HEIGHT}px` }}
                    onClick={() => {
                      if (canOpenMatch) onMatchClick(match)
                    }}
                    role={canOpenMatch ? 'button' : undefined}
                    tabIndex={canOpenMatch ? 0 : undefined}
                    onKeyDown={(event) => {
                      if (!canOpenMatch) return
                      if (event.key === 'Enter' || event.key === ' ') {
                        event.preventDefault()
                        onMatchClick(match)
                      }
                    }}
                  >
                    <div className="bracket-node-top">
                      <span className="bracket-match-index">Partido {match.match_number}</span>
                      <div className="bracket-node-tools">
                        <span className="bracket-node-status">{statusLabel}</span>
                        {showActionButton ? (
                          <button
                            className="bracket-node-action"
                            type="button"
                            onClick={(event) => {
                              event.stopPropagation()
                              onMatchClick(match)
                            }}
                          >
                            {matchActionLabel}
                          </button>
                        ) : null}
                      </div>
                    </div>

                    <div
                      className={`bracket-team ${winnerId && winnerId === registrationA?.id ? 'is-winner' : ''} ${
                        !registrationA?.team && registrationB?.team ? 'is-bye' : ''
                      }`}
                    >
                      {registrationA?.seed_number ? <span className="seed-badge">#{registrationA.seed_number}</span> : null}
                      <span className="team-name">{getTeamLabel(registrationA, registrationB, isPlaceholder)}</span>
                    </div>

                    <div
                      className={`bracket-team ${winnerId && winnerId === registrationB?.id ? 'is-winner' : ''} ${
                        !registrationB?.team && registrationA?.team ? 'is-bye' : ''
                      }`}
                    >
                      {registrationB?.seed_number ? <span className="seed-badge">#{registrationB.seed_number}</span> : null}
                      <span className="team-name">{getTeamLabel(registrationB, registrationA, isPlaceholder)}</span>
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
    </div>
  )
}
