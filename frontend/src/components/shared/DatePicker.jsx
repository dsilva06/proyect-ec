import { useEffect, useMemo, useRef, useState } from 'react'

const WEEKDAYS = ['L', 'M', 'X', 'J', 'V', 'S', 'D']
const MONTHS = [
  'Enero',
  'Febrero',
  'Marzo',
  'Abril',
  'Mayo',
  'Junio',
  'Julio',
  'Agosto',
  'Septiembre',
  'Octubre',
  'Noviembre',
  'Diciembre',
]

const formatDate = (date) => {
  const year = date.getFullYear()
  const month = String(date.getMonth() + 1).padStart(2, '0')
  const day = String(date.getDate()).padStart(2, '0')
  return `${year}-${month}-${day}`
}

const parseDate = (value) => {
  if (!value) return null
  const parts = value.split('-')
  if (parts.length !== 3) return null
  const [year, month, day] = parts.map((part) => Number(part))
  if (!year || !month || !day) return null
  return new Date(year, month - 1, day)
}

export default function DatePicker({ value, onChange, placeholder = 'Selecciona fecha' }) {
  const containerRef = useRef(null)
  const [isOpen, setIsOpen] = useState(false)
  const [viewDate, setViewDate] = useState(() => parseDate(value) || new Date())

  const selectedDate = useMemo(() => parseDate(value), [value])

  useEffect(() => {
    if (selectedDate) {
      setViewDate(selectedDate)
    }
  }, [selectedDate])

  useEffect(() => {
    const handleClickOutside = (event) => {
      if (containerRef.current && !containerRef.current.contains(event.target)) {
        setIsOpen(false)
      }
    }

    document.addEventListener('mousedown', handleClickOutside)
    return () => document.removeEventListener('mousedown', handleClickOutside)
  }, [])

  const year = viewDate.getFullYear()
  const month = viewDate.getMonth()
  const firstOfMonth = new Date(year, month, 1)
  const daysInMonth = new Date(year, month + 1, 0).getDate()
  const startOffset = (firstOfMonth.getDay() + 6) % 7

  const days = Array.from({ length: startOffset + daysInMonth }, (_, index) => {
    if (index < startOffset) return null
    return index - startOffset + 1
  })

  const handleSelect = (day) => {
    const date = new Date(year, month, day)
    onChange(formatDate(date))
    setIsOpen(false)
  }

  const handlePrev = () => {
    setViewDate(new Date(year, month - 1, 1))
  }

  const handleNext = () => {
    setViewDate(new Date(year, month + 1, 1))
  }

  const displayValue = selectedDate ? formatDate(selectedDate) : ''

  return (
    <div className="date-picker" ref={containerRef}>
      <button
        type="button"
        className="date-picker-input"
        onClick={() => setIsOpen((prev) => !prev)}
      >
        {displayValue || placeholder}
      </button>

      {isOpen && (
        <div className="calendar-popover">
          <div className="calendar-header">
            <button type="button" className="ghost-button" onClick={handlePrev}>
              ‹
            </button>
            <span>{MONTHS[month]} {year}</span>
            <button type="button" className="ghost-button" onClick={handleNext}>
              ›
            </button>
          </div>
          <div className="calendar-weekdays">
            {WEEKDAYS.map((day) => (
              <span key={day}>{day}</span>
            ))}
          </div>
          <div className="calendar-grid">
            {days.map((day, index) => (
              <button
                key={`${day ?? 'empty'}-${index}`}
                type="button"
                className={`calendar-day${!day ? ' is-empty' : ''}${
                  selectedDate && day && selectedDate.getFullYear() === year && selectedDate.getMonth() === month && selectedDate.getDate() === day
                    ? ' is-selected'
                    : ''
                }`}
                onClick={() => day && handleSelect(day)}
                disabled={!day}
              >
                {day || ''}
              </button>
            ))}
          </div>
        </div>
      )}
    </div>
  )
}
