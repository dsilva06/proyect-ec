import { useMemo, useState, useEffect } from 'react'
import DatePicker from './DatePicker'

const normalizeDateTime = (value) => {
  if (!value) return { date: '', time: '' }
  const normalized = value.replace(' ', 'T')
  if (!normalized.includes('T')) return { date: value, time: '' }
  const date = normalized.slice(0, 10)
  const time = normalized.length >= 16 ? normalized.slice(11, 16) : ''
  return { date, time }
}

export default function DateTimePicker({ value, onChange }) {
  const normalized = useMemo(() => normalizeDateTime(value), [value])
  const [time, setTime] = useState(normalized.time)

  useEffect(() => {
    setTime(normalized.time)
  }, [normalized.time])

  const handleDateChange = (dateValue) => {
    const nextTime = time || '00:00'
    onChange(dateValue ? `${dateValue}T${nextTime}` : '')
  }

  const handleTimeChange = (event) => {
    const nextTime = event.target.value
    setTime(nextTime)
    if (normalized.date) {
      onChange(`${normalized.date}T${nextTime || '00:00'}`)
    }
  }

  return (
    <div className="date-time-picker">
      <DatePicker value={normalized.date} onChange={handleDateChange} placeholder="Selecciona fecha" />
      <input
        type="time"
        value={time}
        onChange={handleTimeChange}
        className="time-input"
      />
    </div>
  )
}
