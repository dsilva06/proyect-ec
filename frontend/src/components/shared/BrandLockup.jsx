import { Link } from 'react-router-dom'
import estarsSuiteLogo from '../../assets/estars-suite-logo.png'
import estarsSuiteMark from '../../assets/estars-suite-mark.png'

export default function BrandLockup({
  to = '/',
  subtitle = 'Tournament Hub',
  className = '',
  variant = 'full',
  logoClassName = '',
  textClassName = '',
}) {
  const isCompact = variant === 'compact'
  const logoSrc = isCompact ? estarsSuiteMark : estarsSuiteLogo

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
