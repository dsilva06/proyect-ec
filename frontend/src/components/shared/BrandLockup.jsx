import { Link } from 'react-router-dom'
import estarsLogo from '../../assets/estars-logo.png'
import estarsMark from '../../assets/estars-mark.png'

export default function BrandLockup({
  to = '/',
  subtitle = 'Tournament Hub',
  className = '',
  variant = 'full',
  logoClassName = '',
  textClassName = '',
}) {
  const isCompact = variant === 'compact'
  const logoSrc = isCompact ? estarsMark : estarsLogo

  return (
    <Link to={to} className={`brand-lockup ${className}`.trim()}>
      <img className={`brand-logo ${logoClassName}`.trim()} src={logoSrc} alt="ESTARS PADEL TOUR" />
      <span className={`brand-copy ${textClassName} ${isCompact ? 'is-compact' : ''}`.trim()}>
        <span className="brand-heading">ESTARS PADEL TOUR</span>
        <span className="brand-subtitle">{subtitle}</span>
      </span>
    </Link>
  )
}
