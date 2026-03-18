import { Link } from 'react-router-dom'
import estarsLogo from '../../assets/estars-logo.png'

export default function BrandLockup({
  to = '/',
  subtitle = 'Tournament Hub',
  className = '',
  logoClassName = '',
  textClassName = '',
}) {
  return (
    <Link to={to} className={`brand-lockup ${className}`.trim()}>
      <img className={`brand-logo ${logoClassName}`.trim()} src={estarsLogo} alt="ESTARS PADEL TOUR" />
      <span className={`brand-copy ${textClassName}`.trim()}>
        <span className="brand-heading">ESTARS PADEL TOUR</span>
        <span className="brand-subtitle">{subtitle}</span>
      </span>
    </Link>
  )
}
