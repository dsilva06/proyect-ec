import { useEffect, useMemo, useRef, useState } from 'react'

const ITEM_HEIGHT = 36
const CENTER_PADDING = ITEM_HEIGHT * 2
const MONTH_LABELS = ['Ene', 'Feb', 'Mar', 'Abr', 'May', 'Jun', 'Jul', 'Ago', 'Sep', 'Oct', 'Nov', 'Dic']

const pad2 = (value) => String(value).padStart(2, '0')

const clamp = (value, min, max) => Math.min(max, Math.max(min, value))

const getDaysInMonth = (year, month) => new Date(year, month, 0).getDate()

const normalizeMinute = (minute, step) => {
  const safeStep = Number(step) > 0 ? Number(step) : 5
  const rounded = Math.round(Number(minute || 0) / safeStep) * safeStep
  return clamp(rounded, 0, 59)
}

const parseDatePart = (raw) => {
  if (!raw) return null
  const parts = raw.split('-').map((part) => Number(part))
  if (parts.length !== 3 || parts.some((part) => !Number.isFinite(part))) return null
  const [year, month, day] = parts
  if (!year || month < 1 || month > 12 || day < 1 || day > 31) return null
  return { year, month, day }
}

const resolveMinDate = (minDate) => {
  if (!minDate) return null
  if (minDate === 'today') {
    const now = new Date()
    return {
      year: now.getFullYear(),
      month: now.getMonth() + 1,
      day: now.getDate(),
    }
  }
  return parseDatePart(minDate)
}

const asDateNumber = (dateParts) => (
  (Number(dateParts?.year) * 10000) + (Number(dateParts?.month) * 100) + Number(dateParts?.day)
)

const clampSelectionToMinDate = (selection, minDate) => {
  if (!minDate) return selection
  if (asDateNumber(selection) >= asDateNumber(minDate)) return selection
  return {
    ...selection,
    year: minDate.year,
    month: minDate.month,
    day: minDate.day,
  }
}

const parseTimePart = (raw) => {
  if (!raw) return null
  const parts = raw.split(':')
  if (parts.length < 2) return null
  const hour = Number(parts[0])
  const minute = Number(parts[1])
  if (!Number.isFinite(hour) || !Number.isFinite(minute)) return null
  return {
    hour: clamp(hour, 0, 23),
    minute: clamp(minute, 0, 59),
  }
}

const parseIncoming = (value, mode, minuteStep) => {
  const now = new Date()
  const fallback = {
    year: now.getFullYear(),
    month: now.getMonth() + 1,
    day: now.getDate(),
    hour: now.getHours(),
    minute: normalizeMinute(now.getMinutes(), minuteStep),
  }

  if (!value) return fallback

  const normalized = String(value).trim().replace(' ', 'T')

  if (mode === 'time') {
    const parsed = parseTimePart(normalized.includes('T') ? normalized.split('T')[1] : normalized)
    return parsed ? { ...fallback, ...parsed, minute: normalizeMinute(parsed.minute, minuteStep) } : fallback
  }

  const [datePart, timePart] = normalized.split('T')
  const parsedDate = parseDatePart(datePart)
  const parsedTime = parseTimePart(timePart || '')
  const next = {
    ...fallback,
    ...(parsedDate || {}),
    ...(parsedTime || {}),
  }
  next.minute = normalizeMinute(next.minute, minuteStep)

  const maxDay = getDaysInMonth(next.year, next.month)
  next.day = clamp(next.day, 1, maxDay)

  return next
}

const formatOutput = (selection, mode) => {
  const datePart = `${selection.year}-${pad2(selection.month)}-${pad2(selection.day)}`
  const timePart = `${pad2(selection.hour)}:${pad2(selection.minute)}`

  if (mode === 'date') return datePart
  if (mode === 'time') return timePart
  return `${datePart}T${timePart}`
}

const formatDisplay = (selection, mode) => {
  const datePart = `${pad2(selection.day)}/${pad2(selection.month)}/${selection.year}`
  const timePart = `${pad2(selection.hour)}:${pad2(selection.minute)}`

  if (mode === 'date') return datePart
  if (mode === 'time') return timePart
  return `${datePart} • ${timePart}`
}

function WheelColumn({ options, selectedValue, onSelect }) {
  const columnRef = useRef(null)
  const syncLockRef = useRef(false)

  useEffect(() => {
    const node = columnRef.current
    if (!node) return
    const index = options.findIndex((option) => String(option.value) === String(selectedValue))
    if (index < 0) return
    syncLockRef.current = true
    node.scrollTop = index * ITEM_HEIGHT
    const timer = setTimeout(() => {
      syncLockRef.current = false
    }, 80)
    return () => clearTimeout(timer)
  }, [options, selectedValue])

  const handleScroll = () => {
    const node = columnRef.current
    if (!node || syncLockRef.current) return

    const index = clamp(Math.round(node.scrollTop / ITEM_HEIGHT), 0, Math.max(0, options.length - 1))
    const next = options[index]
    if (!next) return
    onSelect(next.value)
  }

  return (
    <div className="wheel-column-wrap">
      <div className="wheel-column" ref={columnRef} onScroll={handleScroll}>
        <div style={{ height: `${CENTER_PADDING}px` }} />
        {options.map((option) => (
          <button
            key={String(option.value)}
            type="button"
            className={`wheel-item ${String(option.value) === String(selectedValue) ? 'is-active' : ''}`}
            onClick={() => onSelect(option.value)}
          >
            {option.label}
          </button>
        ))}
        <div style={{ height: `${CENTER_PADDING}px` }} />
      </div>
      <div className="wheel-column-highlight" aria-hidden="true" />
    </div>
  )
}

export default function WheelDateTimePicker({
  mode = 'datetime',
  value,
  onChange,
  placeholder = 'Selecciona',
  minuteStep = 5,
  minDate = null,
  disabled = false,
}) {
  const containerRef = useRef(null)
  const minDateParts = useMemo(() => resolveMinDate(minDate), [minDate])
  const [isOpen, setIsOpen] = useState(false)
  const [selection, setSelection] = useState(() =>
    clampSelectionToMinDate(parseIncoming(value, mode, minuteStep), mode === 'time' ? null : minDateParts),
  )

  useEffect(() => {
    const parsed = parseIncoming(value, mode, minuteStep)
    setSelection(clampSelectionToMinDate(parsed, mode === 'time' ? null : minDateParts))
  }, [value, mode, minuteStep, minDateParts])

  useEffect(() => {
    const handleOutside = (event) => {
      if (containerRef.current && !containerRef.current.contains(event.target)) {
        setIsOpen(false)
      }
    }
    document.addEventListener('mousedown', handleOutside)
    return () => document.removeEventListener('mousedown', handleOutside)
  }, [])

  const yearOptions = useMemo(() => {
    const currentYear = new Date().getFullYear()
    const baselineMinYear = minDateParts ? minDateParts.year : currentYear - 6
    const minYear = Math.min(baselineMinYear, selection.year)
    const maxYear = Math.max(currentYear + 6, selection.year + 2)
    return Array.from({ length: maxYear - minYear + 1 }, (_, index) => {
      const year = minYear + index
      return { value: year, label: String(year) }
    })
  }, [selection.year, minDateParts])

  const monthOptions = useMemo(() => {
    const minMonth = (minDateParts && selection.year === minDateParts.year) ? minDateParts.month : 1
    return Array.from({ length: 12 - minMonth + 1 }, (_, index) => {
      const month = minMonth + index
      return { value: month, label: MONTH_LABELS[month - 1] }
    })
  }, [selection.year, minDateParts])

  const dayOptions = useMemo(() => {
    const days = getDaysInMonth(selection.year, selection.month)
    const minDay = (
      minDateParts
      && selection.year === minDateParts.year
      && selection.month === minDateParts.month
    )
      ? minDateParts.day
      : 1
    return Array.from({ length: days - minDay + 1 }, (_, index) => {
      const day = minDay + index
      return { value: day, label: pad2(day) }
    })
  }, [selection.year, selection.month, minDateParts])

  const hourOptions = useMemo(
    () => Array.from({ length: 24 }, (_, index) => ({ value: index, label: pad2(index) })),
    [],
  )

  const minuteOptions = useMemo(() => {
    const step = Math.max(1, Number(minuteStep) || 5)
    const options = []
    for (let minute = 0; minute < 60; minute += step) {
      options.push({ value: minute, label: pad2(minute) })
    }
    if (!options.some((item) => item.value === selection.minute)) {
      options.push({ value: selection.minute, label: pad2(selection.minute) })
      options.sort((a, b) => a.value - b.value)
    }
    return options
  }, [minuteStep, selection.minute])

  const commit = (patch) => {
    setSelection((previous) => {
      const next = { ...previous, ...patch }
      if (patch.year || patch.month || patch.day) {
        const maxDay = getDaysInMonth(next.year, next.month)
        next.day = clamp(next.day, 1, maxDay)
      }
      if (patch.minute !== undefined) {
        next.minute = normalizeMinute(next.minute, minuteStep)
      }
      const constrained = clampSelectionToMinDate(next, mode === 'time' ? null : minDateParts)

      const nextValue = formatOutput(constrained, mode)
      if (nextValue !== formatOutput(previous, mode)) {
        onChange?.(nextValue)
      }
      return constrained
    })
  }

  const hasValue = Boolean(value)
  const displayValue = hasValue ? formatDisplay(selection, mode) : placeholder

  return (
    <div className="wheel-picker" ref={containerRef}>
      <button
        type="button"
        className="wheel-picker-trigger"
        onClick={() => !disabled && setIsOpen((current) => !current)}
        disabled={disabled}
      >
        <span className={hasValue ? '' : 'is-placeholder'}>{displayValue}</span>
      </button>

      {isOpen ? (
        <div className={`wheel-picker-popover wheel-picker-popover-${mode}`}>
          <div className="wheel-columns">
            {mode !== 'time' ? (
              <>
                <WheelColumn options={dayOptions} selectedValue={selection.day} onSelect={(day) => commit({ day })} />
                <WheelColumn options={monthOptions} selectedValue={selection.month} onSelect={(month) => commit({ month })} />
                <WheelColumn options={yearOptions} selectedValue={selection.year} onSelect={(year) => commit({ year })} />
              </>
            ) : null}
            {mode !== 'date' ? (
              <>
                <WheelColumn options={hourOptions} selectedValue={selection.hour} onSelect={(hour) => commit({ hour })} />
                <WheelColumn options={minuteOptions} selectedValue={selection.minute} onSelect={(minute) => commit({ minute })} />
              </>
            ) : null}
          </div>
          <div className="wheel-picker-footer">
            <button type="button" className="secondary-button" onClick={() => setIsOpen(false)}>
              Listo
            </button>
          </div>
        </div>
      ) : null}
    </div>
  )
}
