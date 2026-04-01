import { Link } from 'react-router-dom'
import estarsLogo from '../../assets/estars-logo-main.svg'
import estarsMark from '../../assets/estars-mark.png'

export default function BrandLockup({
  to = '/',
  subtitle = 'Centro de torneos',
  className = '',
  variant = 'full',
  logoClassName = '',
  textClassName = '',
}) {
  const isCompact = variant === 'compact'
  const logoSrc = isCompact ? estarsMark : estarsLogo
  const subtitleClassName = ['brand-subtitle', textClassName].filter(Boolean).join(' ')

  return (
    <Link to={to} className={`brand-lockup brand-lockup--${variant} ${className}`.trim()}>
      <img
        className={`brand-logo ${logoClassName}`.trim()}
        src={logoSrc}
        alt={isCompact ? 'ESTARS PADEL TOUR icon' : 'ESTARS PADEL TOUR logo'}
      />
      {subtitle ? (
        <span className={subtitleClassName}>{subtitle}</span>
      ) : null}
    </Link>
  )
}
