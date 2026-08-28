import { Fragment, useEffect, useMemo, useState, type FormEvent, type ReactNode } from 'react'
import {
  api,
  clearSession,
  defaultWorkspace,
  loadSession,
  saveSession,
  workspacesOf,
  type Session,
  type Workspace,
} from './session'

type PersonRow = {
  id: string
  public_id: string
  first_name: string
  last_name: string
  kind?: string
  phone_e164?: string
  email?: string
  access?: 'guardian' | 'finance'
  enrollments?: Array<{ id: string; school_id: string }>
}

type FamilyMemberRow = PersonRow & {
  person_id?: string
  role_in_family?: string
  relationship_types?: string[]
  has_account?: boolean
  invitation_pending?: boolean
}

type FamilyRow = {
  id: string
  label: string
  primary_language?: string
  members: FamilyMemberRow[]
}

type TransferRow = {
  id: string
  status: string
  person: { id: string; first_name: string; last_name: string } | null
  origin_school?: string | null
  destination_school?: string | null
  parent_approved_at?: string | null
}

type AttendanceRow = {
  id: string
  date: string
  session: string
  status: string
}

type ConsentRow = {
  id: string
  subject_person_id: string
  school_name?: string | null
  scope: string
  purpose: string
  active: boolean
  granted_at?: string | null
}

type LinkRequestRow = {
  id: string
  school_name?: string | null
  status: string
  expires_at?: string
}

type AccessLogRow = {
  id: string
  occurred_at?: string | null
  action: string
  resource_type: string
  outcome: string
}

type PersonMini = { id: string; first_name: string; last_name: string }

type GradeRow = { id: string; name: string; stage?: string }

type SchoolNetwork = {
  id: string
  name: string
  campuses: Array<{ id: string; name: string; code: string; city: string | null }>
}

type ClassroomRow = {
  id: string
  name: string
  grade_level_id: string
  school_year_id?: string
  capacity?: number | null
  series?: string | null
  main_teacher_person_id?: string | null
  delegate_person_id?: string | null
  vice_delegate_person_id?: string | null
  grade_level?: { id?: string; name: string; stage?: string; stage_label?: string | null; unit_label?: string | null } | null
  main_teacher?: PersonMini | null
  delegate?: PersonMini | null
  vice_delegate?: PersonMini | null
}

type TermRow = { id: string; label: string; sequence: number }

type ClassStudentRow = {
  enrollment_id: string
  person_id: string
  student_number: string | null
  office: 'delegate' | 'vice_delegate' | null
  person: { id: string; public_id: string; first_name: string; last_name: string } | null
}

type ClassFile = {
  classroom: ClassroomRow
  headcount: number
  students: ClassStudentRow[]
  pickup?: Array<{
    student: { id: string; public_id?: string; first_name: string; last_name: string } | null
    adults: Array<{ person: PersonMini | null; via: string }>
  }>
  teachers: Array<{ id: string; person_id: string; subject: string | null; is_main: boolean; person: PersonMini | null }>
  timetable: Array<{
    id: string
    weekday: number
    starts_at: string
    ends_at: string
    subject: string
    room: string | null
    teacher_person_id: string | null
    teacher: PersonMini | null
  }>
  councils: Array<{
    id: string
    academic_term_id: string | null
    term: TermRow | null
    held_on: string
    title: string
    minutes: string | null
    status: string
  }>
  activities: Array<{
    id: string
    type: string
    title: string
    held_on: string
    location: string | null
    notes: string | null
  }>
}

type ExpenseRow = {
  id: string
  kind: string
  label: string
  category: string
  amount: number
  spent_on: string
  vendor: string | null
}

type YearRow = {
  id: string
  label: string
  is_current: boolean
  starts_on?: string
  ends_on?: string
}

type FeeItemRow = {
  id?: string
  code?: string
  label: string
  amount: number
  due_on: string
  category: string
  is_recurring?: boolean
}

type FeeScheduleRow = {
  id: string
  school_year_id: string
  grade_level_id: string | null
  name: string
  status: string
  locked: boolean
  total_amount: number
  copied_from_schedule_id?: string | null
  unlock_requested_at?: string | null
  unlock_request_reason?: string | null
  grade_level?: { id: string; name: string } | null
  school_year?: { id: string; label: string } | null
  items: FeeItemRow[]
}

type EnrollmentRow = {
  id: string
  classroom_id: string | null
  status: string
  person: { id: string; public_id: string; first_name: string; last_name: string } | null
  classroom: { id: string; name: string } | null
  invoice: { id: string; number: string; remaining_amount: number; status: string } | null
}

type RosterStudent = {
  enrollment_id: string
  person: { id: string; public_id: string; first_name: string; last_name: string } | null
}

type InvoiceRow = {
  id: string
  number: string
  net_amount: number
  paid_amount: number
  remaining_amount: number
  status: string
  discount_amount: number
  installments: Array<{
    id: string
    due_on: string
    amount: number
    paid_amount: number
    remaining_amount: number
    status: string
  }>
}

type CockpitAction = {
  id: string
  enrollment_id: string
  template_key: string
  title: string
  reason_summary: string
  priority: string
  status: string
  student: { id: string; first_name: string; last_name: string } | null
}

type Cockpit = {
  as_of: string
  attendance: { present: number; absent: number }
  collected_today: number
  outstanding_amount: number
  risk_counts: { low: number; medium: number; high: number; critical: number }
  forecast: {
    week_starting_on: string
    expected_amount: number
    confidence_low_amount: number
    confidence_high_amount: number
  } | null
  actions: CockpitAction[]
  attention?: CockpitAttention[]
}

type CockpitAttention = {
  id: string
  enrollment_id: string
  reason_summary: string
  status: string
  student: { id: string; first_name: string; last_name: string } | null
}

type ParentInboxMessage = {
  id: string
  channel: string
  subject: string
  body: string
  queued_at: string | null
  sent_at: string | null
}

type ChildFinance = {
  remaining_amount: number
  data: Array<{
    school: { id?: string; name: string } | null
    classroom: { name: string } | null
    invoice: InvoiceRow | null
    payments: Array<{ amount: number; received_on: string; receipt_number: string | null }>
  }>
}

type StudentOverview = {
  person: PersonRow
  enrollment: {
    student_number: string | null
    school: { name: string } | null
    classroom: { name: string } | null
  } | null
  attendance: Array<{ id: string; date: string; status: string }>
  finance: {
    remaining_amount: number
    invoice: InvoiceRow | null
    payments: Array<{ amount: number; received_on: string; receipt_number: string | null }>
  }
}

type ReliabilityFactor = {
  event_type: string
  human_label: string
  contribution: number
  event_count: number
}

type ReliabilityScoreView = {
  id: string | null
  subject_type: string
  subject_id: string
  index_type?: string
  value: number | null
  band: string
  displayable?: boolean
  calculator_version: string
  computed_at: string | null
  event_count: number
  minimum_events?: number | null
  factors: ReliabilityFactor[]
}

type ReliabilityOverview = {
  school: ReliabilityScoreView
  families: Array<{
    family_id: string
    students: Array<{ id: string; first_name: string; last_name: string }>
    family_reliability: ReliabilityScoreView
    relationship_health: ReliabilityScoreView
  }>
}

type DirectionTab = 'accueil' | 'famille' | 'classe' | 'finance' | 'caisse' | 'kits' | 'indices'
type TeacherTab = 'classe' | 'appel' | 'kits'
type ParentTab = 'enfants' | 'kits' | 'messages' | 'compte'

const PAGE_SIZE = 40

const DIRECTION_NAV: Array<{ id: DirectionTab; label: string }> = [
  { id: 'accueil', label: 'Aujourd’hui' },
  { id: 'famille', label: 'Familles' },
  { id: 'classe', label: 'Classes' },
  { id: 'finance', label: 'Finance' },
  { id: 'caisse', label: 'Caisse' },
  { id: 'kits', label: 'Kits' },
  { id: 'indices', label: 'Indices' },
]

const TEACHER_NAV: Array<{ id: TeacherTab; label: string }> = [
  { id: 'appel', label: 'Appel' },
  { id: 'classe', label: 'Classe' },
  { id: 'kits', label: 'Kits' },
]

const PARENT_NAV: Array<{ id: ParentTab; label: string }> = [
  { id: 'enfants', label: 'Enfants' },
  { id: 'kits', label: 'Kits' },
  { id: 'messages', label: 'Messages' },
  { id: 'compte', label: 'Compte' },
]

const WORKSPACE_LABEL: Record<Workspace, string> = {
  direction: 'Direction',
  teacher: 'Classe',
  parent: 'Famille',
  student: 'Élève',
}

export default function App() {
  const [session, setSession] = useState<Session | null>(() => loadSession())
  const [workspace, setWorkspace] = useState<Workspace>(() => {
    const current = loadSession()
    return current ? defaultWorkspace(current) : 'parent'
  })
  const [directionTab, setDirectionTab] = useState<DirectionTab>('accueil')
  const [teacherTab, setTeacherTab] = useState<TeacherTab>('appel')
  const [parentTab, setParentTab] = useState<ParentTab>('enfants')

  function signedIn(next: Session) {
    const schoolId = next.schoolId ?? next.schools[0]?.id
    const stored = { ...next, schoolId }
    saveSession(stored)
    setSession(stored)
    setWorkspace(defaultWorkspace(stored))
  }

  function signOut() {
    clearSession()
    setSession(null)
  }

  if (!session) {
    return <LoginScreen onSuccess={signedIn} />
  }

  const schoolName = session.schools.find((row) => row.id === session.schoolId)?.name ?? session.schools[0]?.name
  const spaces = workspacesOf(session)

  return (
    <div className="min-h-svh">
      <header className="sticky top-0 z-20 border-b border-black/10 bg-fanabe-ink text-white">
        <div className="flex h-11 items-center gap-2 px-3">
          <Logo />
          {schoolName && (workspace === 'direction' || workspace === 'teacher') ? (
            <span className="max-w-40 truncate text-xs text-white/55">{schoolName}</span>
          ) : null}
          {spaces.length > 1 ? (
            <nav className="ml-1 flex min-w-0 items-center gap-0.5" aria-label="Espaces">
              {spaces.map((space) => (
                <button
                  key={space}
                  type="button"
                  className={chromeTab(workspace === space)}
                  onClick={() => setWorkspace(space)}
                >
                  {WORKSPACE_LABEL[space]}
                </button>
              ))}
            </nav>
          ) : (
            <span className="text-xs font-medium text-white/70">{WORKSPACE_LABEL[workspace]}</span>
          )}
          <div className="ml-auto flex min-w-0 items-center gap-2">
            {session.schools.length > 1 && (workspace === 'direction' || workspace === 'teacher') ? (
              <select
                className="h-7 max-w-36 truncate rounded-md border-0 bg-white/10 px-2 text-xs text-white"
                value={session.schoolId ?? session.schools[0].id}
                onChange={(event) => signedIn({ ...session, schoolId: event.target.value })}
              >
                {session.schools.map((school) => (
                  <option key={school.id} value={school.id} className="text-fanabe-ink">
                    {school.name}
                  </option>
                ))}
              </select>
            ) : null}
            <span className="hidden truncate text-xs text-white/55 sm:inline">
              {session.person.first_name} {session.person.last_name}
            </span>
            <button type="button" className="h-7 rounded-md px-2 text-xs text-white/70 hover:bg-white/10" onClick={signOut}>
              Sortir
            </button>
          </div>
        </div>
        {workspace === 'direction' ? (
          <nav className="flex h-9 items-center gap-1 overflow-x-auto border-t border-white/10 px-3" aria-label="Direction">
            {DIRECTION_NAV.map((item) => (
              <button key={item.id} type="button" className={chromeTab(directionTab === item.id)} onClick={() => setDirectionTab(item.id)}>
                {item.label}
              </button>
            ))}
          </nav>
        ) : null}
        {workspace === 'teacher' ? (
          <nav className="flex h-9 items-center gap-1 overflow-x-auto border-t border-white/10 px-3" aria-label="Classe">
            {TEACHER_NAV.map((item) => (
              <button key={item.id} type="button" className={chromeTab(teacherTab === item.id)} onClick={() => setTeacherTab(item.id)}>
                {item.label}
              </button>
            ))}
          </nav>
        ) : null}
        {workspace === 'parent' ? (
          <nav className="flex h-9 items-center gap-1 overflow-x-auto border-t border-white/10 px-3" aria-label="Famille">
            {PARENT_NAV.map((item) => (
              <button key={item.id} type="button" className={chromeTab(parentTab === item.id)} onClick={() => setParentTab(item.id)}>
                {item.label}
              </button>
            ))}
          </nav>
        ) : null}
      </header>
      {workspace === 'direction' ? (
        <DirectionScreen session={session} tab={directionTab} onTab={setDirectionTab} />
      ) : workspace === 'teacher' ? (
        <TeacherScreen session={session} tab={teacherTab} />
      ) : workspace === 'student' ? (
        <StudentScreen session={session} />
      ) : (
        <ParentScreen session={session} tab={parentTab} />
      )}
    </div>
  )
}

function Logo() {
  return (
    <div className="flex shrink-0 items-center gap-1.5">
      <span className="grid h-6 w-6 place-items-center rounded-md bg-fanabe-leaf">
        <svg viewBox="0 0 24 24" className="h-3.5 w-3.5 fill-white" aria-hidden>
          <path d="M12 3c.5 3.4 2.8 5.8 6.5 6.6C18.2 16.2 14.4 19.5 12 20.8 9.6 19.5 5.8 16.2 5.5 9.6 9.2 8.8 11.5 6.4 12 3z" />
        </svg>
      </span>
      <span className="text-sm font-semibold tracking-tight">FANABE</span>
    </div>
  )
}

function chromeTab(active: boolean): string {
  return `h-7 shrink-0 rounded-md px-2.5 text-xs font-medium ${
    active ? 'bg-white text-fanabe-ink' : 'text-white/70 hover:bg-white/10'
  }`
}

function formatAr(amount: number): string {
  return `${new Intl.NumberFormat('fr-FR').format(amount)} Ar`
}

type KitOffer = {
  tier: string
  tier_label?: string
  brand: string | null
  unit_amount: number
  quantity?: number
  line_amount?: number
}
type KitNeed = { id?: string; label: string; quantity: number; notes?: string | null; offers?: KitOffer[] }
type KitPackRow = {
  id: string
  tier?: string
  tier_label: string
  total_amount: number
  pay_instruction: string
  supplier?: { name: string } | null
}
type KitDefinitionRow = {
  id: string
  name: string
  grade_level?: string | null
  grade_level_id?: string | null
  price_source?: string
  price_source_label?: string
  choice_copy?: string
  needs?: KitNeed[]
  packs: KitPackRow[]
}
type KitOrderRow = {
  id: string
  enrollment_id?: string
  kit_definition_id?: string | null
  status: string
  status_label?: string
  fulfillment?: string
  fulfillment_label?: string
  total_amount: number
  student_name: string
  pay_instruction: string
}

const KIT_TIERS = [
  { id: 'eco', label: 'Éco' },
  { id: 'standard', label: 'Standard' },
  { id: 'premium', label: 'Luxe' },
] as const

function kitOffer(need: KitNeed, tier: string): KitOffer | undefined {
  return need.offers?.find((row) => row.tier === tier || (tier === 'premium' && row.tier === 'luxe'))
}

function KitSupplyTable({ definition }: { definition: KitDefinitionRow }) {
  const needs = definition.needs ?? []
  if (needs.length === 0) {
    return <p className="mt-1 text-xs text-neutral-500">Aucune fourniture listée pour ce niveau.</p>
  }
  return (
    <div className="mt-2 overflow-x-auto">
      <table className="w-full min-w-[36rem] text-sm">
        <thead className="text-left text-[11px] uppercase tracking-wide text-neutral-500">
          <tr>
            <th className="py-1 pr-2 font-medium">Article</th>
            <th className="py-1 pr-2 font-medium">Qté</th>
            {KIT_TIERS.map((tier) => (
              <th key={tier.id} className="py-1 pr-2 font-medium">
                {tier.label}
              </th>
            ))}
          </tr>
        </thead>
        <tbody>
          {needs.map((need, index) => (
            <tr key={need.id ?? `${need.label}-${index}`} className="border-t border-black/5">
              <td className="py-1.5 pr-2 font-medium">{need.label}</td>
              <td className="py-1.5 pr-2 tabular-nums">{need.quantity}</td>
              {KIT_TIERS.map((tier) => {
                const offer = kitOffer(need, tier.id)
                return (
                  <td key={tier.id} className="py-1.5 pr-2 text-xs text-neutral-600">
                    {offer?.brand ? <span className="block">{offer.brand}</span> : null}
                    {offer ? <span className="tabular-nums">{formatAr(offer.unit_amount)}</span> : '—'}
                  </td>
                )
              })}
            </tr>
          ))}
        </tbody>
        <tfoot>
          <tr className="border-t border-black/10 text-xs">
            <td className="py-1.5 pr-2 font-medium" colSpan={2}>
              Total
            </td>
            {KIT_TIERS.map((tier) => {
              const pack = definition.packs.find((row) => row.tier === tier.id || (tier.id === 'premium' && row.tier === 'luxe'))
              return (
                <td key={tier.id} className="py-1.5 pr-2 font-medium tabular-nums">
                  {pack ? formatAr(pack.total_amount) : '—'}
                </td>
              )
            })}
          </tr>
        </tfoot>
      </table>
    </div>
  )
}

function certificateStatusLabel(status: string): string {
  if (status === 'valid') return 'Valide'
  if (status === 'revoked') return 'Révoqué'
  if (status === 'expired') return 'Expiré'
  if (status === 'issued') return 'Émis'
  return status
}

type BulletinRow = {
  overall_average: number | null
  subjects?: Array<{
    subject: string | null
    average: number | null
    entries: Array<{ value: number; assessed_on: string | null }>
  }>
}

function formatDate(value: string): string {
  return new Date(`${value}T00:00:00`).toLocaleDateString('fr-FR', {
    day: 'numeric',
    month: 'short',
  })
}

function kindLabel(kind?: string): string {
  if (kind === 'student') return 'Élève'
  if (kind === 'parent') return 'Parent'
  if (kind === 'staff') return 'Personnel'
  return kind ?? 'Personne'
}

function relationshipLabel(type?: string): string {
  if (type === 'parent_of') return 'Parent'
  if (type === 'guardian_of') return 'Tuteur'
  if (type === 'financial_contact_for') return 'Contact financier'
  if (type === 'pickup_authorized_for') return 'Autorisé à récupérer'
  return type ?? ''
}

function memberPersonId(member: FamilyMemberRow): string {
  return member.id || member.person_id || ''
}

function familySearchText(family: FamilyRow): string {
  const names = family.members.map((member) => `${member.first_name ?? ''} ${member.last_name ?? ''}`).join(' ')
  return `${family.label} ${names}`
}

function transferLabel(status?: string): string {
  if (status === 'pending_parent') return 'Attente parent'
  if (status === 'pending_origin_school') return 'Attente école'
  if (status === 'approved') return 'Validé'
  if (status === 'completed') return 'Terminé'
  if (status === 'rejected') return 'Refusé'
  if (status === 'cancelled') return 'Annulé'
  return status ?? ''
}

function consentLabel(scope?: string): string {
  if (scope === 'identity.core') return 'Identité'
  if (scope === 'identity.contact') return 'Coordonnées'
  if (scope === 'academic.records') return 'Bulletins'
  if (scope === 'academic.attendance') return 'Présence'
  if (scope === 'finance.history') return 'Écolage'
  if (scope === 'documents.external') return 'Documents'
  if (scope === 'documents.certificates') return 'Certificats'
  return scope ?? ''
}

function feeStatusLabel(status?: string, locked?: boolean): string {
  if (locked || status === 'active') return 'Verrouillé'
  if (status === 'pending_validation') return '1re validation'
  if (status === 'draft') return 'Brouillon'
  return status ?? ''
}

function invoiceLabel(status?: string): string {
  if (status === 'paid') return 'Soldée'
  if (status === 'partially_paid') return 'Partiel'
  if (status === 'issued') return 'À payer'
  return status ?? ''
}

function scoreLabel(score?: ReliabilityScoreView): string {
  if (!score) return '—'
  if (score.displayable === false || score.band === 'insufficient') {
    const need = score.minimum_events ?? 5
    return `Pas assez de faits (${score.event_count}/${need})`
  }
  const bands: Record<string, string> = { high: 'Élevée', medium: 'Moyenne', low: 'Faible' }
  const band = bands[score.band] ?? score.band
  return score.value === null ? band : `${score.value} · ${band}`
}

function attendanceLabel(status?: string): string {
  if (status === 'present') return 'Présent'
  if (status === 'absent') return 'Absent'
  if (status === 'late') return 'Retard'
  if (status === 'excused') return 'Excusé'
  return status ?? ''
}

function Banner({ message, onClear }: { message: string; onClear: () => void }) {
  const error = /impossible|invalide|erreur|appartient pas/i.test(message)
  return (
    <p
      role="status"
      className={`mb-3 flex items-center justify-between gap-3 rounded-md px-3 py-2 text-sm ${
        error ? 'bg-red-50 text-red-900' : 'bg-fanabe-mist text-fanabe-leaf-dark'
      }`}
    >
      <span>{message}</span>
      <button type="button" className="h-6 w-6 text-base" onClick={onClear} aria-label="Fermer">
        ×
      </button>
    </p>
  )
}

async function copyText(value: string): Promise<void> {
  await navigator.clipboard.writeText(value)
}

function matchesQuery(haystack: string, query: string): boolean {
  const needle = query.trim().toLowerCase()
  return needle === '' || haystack.toLowerCase().includes(needle)
}

function pageOf<T>(rows: T[], page: number): T[] {
  const start = (page - 1) * PAGE_SIZE
  return rows.slice(start, start + PAGE_SIZE)
}

function pageCount(total: number): number {
  return Math.max(1, Math.ceil(total / PAGE_SIZE))
}

const inputClass =
  'h-8 w-full rounded-md border border-black/10 bg-white px-2.5 text-sm outline-none ring-fanabe-leaf focus:ring-2'
const btnPrimary =
  'inline-flex h-8 items-center justify-center rounded-md bg-fanabe-leaf px-3 text-sm font-semibold text-white hover:bg-fanabe-leaf-dark disabled:opacity-50'
const btnBlock = `${btnPrimary} w-full`
const btnGhost =
  'inline-flex h-8 items-center justify-center rounded-md border border-black/10 bg-white px-3 text-sm font-medium hover:bg-black/5 disabled:opacity-50'

function Field({ label, children }: { label: string; children: ReactNode }) {
  return (
    <label className="block text-xs font-medium text-neutral-600">
      {label}
      {children}
    </label>
  )
}

function Panel({ children, className = '' }: { children: ReactNode; className?: string }) {
  return <section className={`rounded-lg border border-black/8 bg-fanabe-paper ${className}`}>{children}</section>
}

function Pager({ page, total, onPage }: { page: number; total: number; onPage: (page: number) => void }) {
  const pages = pageCount(total)
  if (total <= PAGE_SIZE) {
    return <p className="text-xs text-neutral-500">{total} résultat{total > 1 ? 's' : ''}</p>
  }
  return (
    <div className="flex items-center justify-between gap-2 text-xs text-neutral-600">
      <span>
        {total} · page {page}/{pages}
      </span>
      <div className="flex gap-1">
        <button type="button" className={btnGhost} disabled={page <= 1} onClick={() => onPage(page - 1)}>
          Préc.
        </button>
        <button type="button" className={btnGhost} disabled={page >= pages} onClick={() => onPage(page + 1)}>
          Suiv.
        </button>
      </div>
    </div>
  )
}

function LoginScreen({ onSuccess }: { onSuccess: (session: Session) => void }) {
  const [mode, setMode] = useState<'login' | 'invite'>('login')
  const [email, setEmail] = useState('direction.antsahabe@fanabe.test')
  const [password, setPassword] = useState('password')
  const [code, setCode] = useState('')
  const [message, setMessage] = useState<string | null>(null)
  const [busy, setBusy] = useState(false)

  async function onSubmit(event: FormEvent) {
    event.preventDefault()
    setBusy(true)
    setMessage(null)
    try {
      const payload =
        mode === 'login'
          ? await api<Session>('/api/v1/auth/login', {
              method: 'POST',
              body: JSON.stringify({ email, password }),
            })
          : await api<Session>('/api/v1/auth/invitations/claim', {
              method: 'POST',
              body: JSON.stringify({ code, email, password }),
            })
      onSuccess(payload)
    } catch (error) {
      setMessage(error instanceof Error ? error.message : 'Connexion impossible.')
    } finally {
      setBusy(false)
    }
  }

  const demos = [
    { label: 'Direction', email: 'direction.antsahabe@fanabe.test' },
    { label: 'Professeur', email: 'teacher.antsahabe@fanabe.test' },
    { label: 'Parent', email: 'parent.andry@fanabe.test' },
    { label: 'Élève', email: 'eleve.fanja@fanabe.test' },
  ]

  return (
    <main className="grid min-h-svh lg:grid-cols-[20rem_1fr]">
      <section className="hidden flex-col justify-between bg-fanabe-ink px-8 py-8 text-white lg:flex">
        <Logo />
        <div>
          <p className="font-display text-3xl leading-tight">L’école, la famille, connectées.</p>
          <p className="mt-3 text-sm leading-relaxed text-white/65">
            Direction, classe, famille, élève — sans SMS, sans encaissement en ligne.
          </p>
        </div>
        <p className="text-xs text-white/40">FANABE · Madagascar</p>
      </section>
      <section className="flex flex-col justify-center px-5 py-10 sm:px-12">
        <div className="lg:hidden">
          <Logo />
        </div>
        <h1 className="mt-6 text-xl font-semibold lg:mt-0">Connexion</h1>
        <div className="mt-4 grid grid-cols-2 gap-1 rounded-md bg-black/5 p-0.5">
          <button type="button" className={modeTab(mode === 'login')} onClick={() => setMode('login')}>
            Compte
          </button>
          <button type="button" className={modeTab(mode === 'invite')} onClick={() => setMode('invite')}>
            Invitation
          </button>
        </div>
        <form onSubmit={onSubmit} className="mt-4 max-w-sm space-y-3" aria-label="Connexion">
          {mode === 'invite' ? (
            <Field label="Code imprimé">
              <input className={inputClass} value={code} onChange={(e) => setCode(e.target.value)} required autoComplete="one-time-code" />
            </Field>
          ) : null}
          <Field label="Email">
            <input className={inputClass} type="email" autoComplete="username" value={email} onChange={(e) => setEmail(e.target.value)} required />
          </Field>
          <Field label="Mot de passe">
            <input className={inputClass} type="password" autoComplete="current-password" value={password} onChange={(e) => setPassword(e.target.value)} required />
          </Field>
          <button type="submit" disabled={busy} className={btnBlock}>
            {busy ? 'Connexion…' : mode === 'login' ? 'Entrer' : 'Activer le compte'}
          </button>
        </form>
        {message ? <Banner message={message} onClear={() => setMessage(null)} /> : null}
        <div className="mt-6 max-w-sm">
          <p className="text-[10px] font-semibold uppercase tracking-wider text-neutral-500">Démo</p>
          <div className="mt-1.5 flex flex-wrap gap-1">
            {demos.map((demo) => (
              <button
                key={demo.email}
                type="button"
                className="h-7 rounded-md bg-white px-2 text-xs shadow-sm"
                onClick={() => {
                  setMode('login')
                  setEmail(demo.email)
                  setPassword('password')
                }}
              >
                {demo.label}
              </button>
            ))}
          </div>
        </div>
      </section>
    </main>
  )
}

function modeTab(active: boolean): string {
  return `h-8 rounded-md text-sm font-medium ${active ? 'bg-white shadow-sm' : 'text-neutral-600'}`
}

function sexLabel(sex?: string): string {
  if (sex === 'female') return 'Fille'
  if (sex === 'male') return 'Garçon'
  return 'Non précisé'
}

function classroomLabel(classroom?: ClassroomRow | null): string {
  if (!classroom) return '—'
  return classroom.grade_level?.name ? `${classroom.name} · ${classroom.grade_level.name}` : classroom.name
}

const WEEKDAY_LABELS = ['Lun', 'Mar', 'Mer', 'Jeu', 'Ven', 'Sam'] as const

function weekdayLabel(day: number): string {
  return WEEKDAY_LABELS[day - 1] ?? String(day)
}

function personLabel(person?: PersonMini | null): string {
  if (!person) return '—'
  return `${person.first_name} ${person.last_name}`
}

function activityTypeLabel(type?: string): string {
  if (type === 'parent_meeting') return 'Réunion parents'
  if (type === 'outing') return 'Sortie'
  if (type === 'celebration') return 'Fête'
  return 'Autre'
}

function councilStatusLabel(status?: string): string {
  if (status === 'held') return 'Tenu'
  return 'Prévu'
}

function expenseKindLabel(kind?: string): string {
  if (kind === 'purchase') return 'Achat'
  return 'Dépense'
}

function expenseCategoryLabel(category?: string): string {
  if (category === 'supplies') return 'Fournitures'
  if (category === 'maintenance') return 'Entretien'
  if (category === 'utilities') return 'Charges'
  if (category === 'transport') return 'Transport'
  if (category === 'food') return 'Alimentation'
  return 'Autre'
}

function officeLabel(office?: string | null): string {
  if (office === 'delegate') return 'Délégué'
  if (office === 'vice_delegate') return 'Vice-délégué'
  return ''
}

function stageLabel(stage?: string | null): string {
  if (stage === 'preschool') return 'Maternelle'
  if (stage === 'primary') return 'Primaire'
  if (stage === 'middle') return 'Collège'
  if (stage === 'high') return 'Lycée'
  return 'Autres'
}

function classroomStage(classroom: ClassroomRow): string {
  return classroom.grade_level?.stage ?? ''
}

function showsDelegate(stage?: string | null): boolean {
  return stage !== 'preschool'
}

function showsCouncil(stage?: string | null): boolean {
  return stage !== 'preschool' && stage !== 'primary'
}

function showsGrades(stage?: string | null): boolean {
  return stage !== 'preschool'
}

function unitLabel(stage?: string | null): string {
  return stage === 'preschool' ? 'Groupe' : 'Classe'
}

function pickupViaLabel(via?: string | null): string {
  if (via === 'parent_of') return 'Parent'
  if (via === 'guardian_of') return 'Tuteur'
  if (via === 'pickup_authorized_for') return 'Autorisé'
  return via ?? ''
}

const GRADE_PACKS: Array<{ id: string; label: string; hint: string }> = [
  { id: 'preschool', label: 'Maternelle', hint: 'PS, MS, GS' },
  { id: 'primary', label: 'Primaire', hint: 'CP – CM2' },
  { id: 'primary_malagasy', label: 'Primaire T1–T5', hint: 'Variante, pas avec CP–CM2' },
  { id: 'middle', label: 'Collège', hint: '6ème – 3ème' },
  { id: 'high', label: 'Lycée', hint: 'Seconde – Terminale' },
]

const STAGE_ORDER = ['preschool', 'primary', 'middle', 'high', '']

function classroomsByStage(rows: ClassroomRow[]): Array<{ stage: string; label: string; rows: ClassroomRow[] }> {
  const buckets = new Map<string, ClassroomRow[]>()
  for (const row of rows) {
    const stage = classroomStage(row)
    const list = buckets.get(stage) ?? []
    list.push(row)
    buckets.set(stage, list)
  }
  return STAGE_ORDER.filter((stage) => buckets.has(stage)).map((stage) => ({
    stage,
    label: stageLabel(stage),
    rows: buckets.get(stage) ?? [],
  }))
}

function gradesByStage(rows: GradeRow[]): Array<{ stage: string; label: string; rows: GradeRow[] }> {
  const buckets = new Map<string, GradeRow[]>()
  for (const row of rows) {
    const stage = row.stage ?? ''
    const list = buckets.get(stage) ?? []
    list.push(row)
    buckets.set(stage, list)
  }
  return STAGE_ORDER.filter((stage) => buckets.has(stage)).map((stage) => ({
    stage,
    label: stageLabel(stage),
    rows: buckets.get(stage) ?? [],
  }))
}

function ClassSection({ title, children }: { title: string; children: ReactNode }) {
  return (
    <div className="border-t border-black/5 px-3 py-3">
      <h3 className="mb-2 text-[11px] font-semibold uppercase tracking-wide text-neutral-500">{title}</h3>
      {children}
    </div>
  )
}

function EnrollmentWizard({
  yearId,
  yearLabel,
  schoolId,
  auth,
  families,
  classrooms,
  busy,
  onBusy,
  onClose,
  onEnrolled,
}: {
  yearId: string
  yearLabel: string
  schoolId: string
  auth: { token: string }
  families: FamilyRow[]
  classrooms: ClassroomRow[]
  busy: boolean
  onBusy: (value: boolean) => void
  onClose: () => void
  onEnrolled: (result: { familyId: string; invitation: string | null }) => Promise<void>
}) {
  const [step, setStep] = useState<1 | 2 | 3 | 'done'>(1)
  const [error, setError] = useState<string | null>(null)
  const [student, setStudent] = useState({ first_name: '', last_name: '', birth_date: '', sex: 'unspecified', classroom_id: '' })
  const [foyerMode, setFoyerMode] = useState<'new' | 'existing'>('new')
  const [familyQuery, setFamilyQuery] = useState('')
  const [familyId, setFamilyId] = useState('')
  const [label, setLabel] = useState('')
  const [parent, setParent] = useState({
    first_name: '',
    last_name: '',
    phone: '',
    email: '',
    relationship: 'parent_of',
  })
  const [invitation, setInvitation] = useState<string | null>(null)
  const [doneName, setDoneName] = useState('')

  const matchedFamilies = families.filter((family) => matchesQuery(familySearchText(family), familyQuery))
  const chosenFamily = families.find((family) => family.id === familyId) ?? null
  const yearClassrooms = classrooms.filter((classroom) => !classroom.school_year_id || classroom.school_year_id === yearId)
  const chosenClassroom = yearClassrooms.find((classroom) => classroom.id === student.classroom_id) ?? null
  const studentReady =
    student.first_name.trim() !== '' &&
    student.last_name.trim() !== '' &&
    student.birth_date !== '' &&
    student.classroom_id !== ''
  const foyerReady =
    foyerMode === 'existing'
      ? familyId !== ''
      : parent.first_name.trim() !== '' && parent.last_name.trim() !== ''

  useEffect(() => {
    function onKey(event: KeyboardEvent) {
      if (event.key === 'Escape' && !busy) onClose()
    }
    window.addEventListener('keydown', onKey)
    return () => window.removeEventListener('keydown', onKey)
  }, [busy, onClose])

  function goFoyer() {
    setError(null)
    setParent((prev) => ({ ...prev, last_name: prev.last_name || student.last_name }))
    setLabel((prev) => prev || student.last_name)
    setStep(2)
  }

  async function submit() {
    onBusy(true)
    setError(null)
    try {
      if (foyerMode === 'existing' && familyId) {
        await api(`/api/v1/schools/${schoolId}/families/${familyId}/children`, {
          ...auth,
          method: 'POST',
          body: JSON.stringify({
            school_year_id: yearId,
            first_name: student.first_name,
            last_name: student.last_name,
            birth_date: student.birth_date,
            sex: student.sex,
            classroom_id: student.classroom_id,
          }),
        })
        setInvitation(null)
        setDoneName(`${student.first_name} ${student.last_name}`)
        setStep('done')
        await onEnrolled({ familyId, invitation: null }).catch(() => undefined)
        return
      }

      const created = await api<{ invitation_code: string; family_id: string }>(`/api/v1/schools/${schoolId}/families`, {
        ...auth,
        method: 'POST',
        body: JSON.stringify({
          school_year_id: yearId,
          label: label || student.last_name,
          relationship: parent.relationship,
          parent: {
            first_name: parent.first_name,
            last_name: parent.last_name,
            phone: parent.phone || undefined,
            email: parent.email || undefined,
          },
          student: {
            first_name: student.first_name,
            last_name: student.last_name,
            birth_date: student.birth_date,
              sex: student.sex,
            },
            classroom_id: student.classroom_id,
          }),
        })
      setInvitation(created.invitation_code)
      setDoneName(`${student.first_name} ${student.last_name}`)
      setStep('done')
      await onEnrolled({ familyId: created.family_id, invitation: created.invitation_code }).catch(() => undefined)
    } catch (err) {
      setError(err instanceof Error ? err.message : 'Inscription impossible.')
    } finally {
      onBusy(false)
    }
  }

  const steps = [
    { id: 1, label: 'Élève' },
    { id: 2, label: 'Foyer' },
    { id: 3, label: 'Confirmer' },
  ] as const

  return (
    <div className="fixed inset-0 z-40 flex items-end justify-center bg-black/40 p-3 sm:items-center" onClick={() => !busy && step !== 'done' && onClose()}>
      <div
        role="dialog"
        aria-modal="true"
        aria-labelledby="enroll-title"
        className="w-full max-w-lg rounded-lg bg-fanabe-paper shadow-xl"
        onClick={(event) => event.stopPropagation()}
      >
        <div className="flex items-center justify-between border-b border-black/5 px-4 py-2.5">
          <div>
            <h2 id="enroll-title" className="text-sm font-semibold">
              Inscrire un élève
            </h2>
            <p className="text-[11px] text-neutral-500">{yearLabel}</p>
          </div>
          <button type="button" className="h-7 w-7 text-lg leading-none text-neutral-500" onClick={onClose} aria-label="Fermer">
            ×
          </button>
        </div>
        {step !== 'done' ? (
          <div className="flex gap-1 border-b border-black/5 px-4 py-2">
            {steps.map((item) => (
              <span
                key={item.id}
                className={`rounded-md px-2 py-1 text-[11px] font-medium ${
                  step === item.id ? 'bg-fanabe-mist text-fanabe-leaf-dark' : 'text-neutral-500'
                }`}
              >
                {item.id} {item.label}
              </span>
            ))}
          </div>
        ) : null}
        <div className="px-4 py-3">
          {error ? <Banner message={error} onClear={() => setError(null)} /> : null}

          {step === 1 ? (
            <div className="space-y-2">
              <p className="text-xs text-neutral-600">D’abord l’élève, ensuite le foyer qui en a la charge.</p>
              <div className="grid grid-cols-2 gap-2">
                <Field label="Prénom">
                  <input className={inputClass} value={student.first_name} onChange={(e) => setStudent({ ...student, first_name: e.target.value })} autoFocus required />
                </Field>
                <Field label="Nom">
                  <input className={inputClass} value={student.last_name} onChange={(e) => setStudent({ ...student, last_name: e.target.value })} required />
                </Field>
              </div>
              <div className="grid grid-cols-2 gap-2">
                <Field label="Naissance">
                  <input className={inputClass} type="date" value={student.birth_date} onChange={(e) => setStudent({ ...student, birth_date: e.target.value })} required />
                </Field>
                <Field label="Sexe">
                  <select className={inputClass} value={student.sex} onChange={(e) => setStudent({ ...student, sex: e.target.value })}>
                    <option value="unspecified">Non précisé</option>
                    <option value="female">Fille</option>
                    <option value="male">Garçon</option>
                  </select>
                </Field>
              </div>
              <Field label="Classe">
                <select
                  className={inputClass}
                  value={student.classroom_id}
                  onChange={(e) => setStudent({ ...student, classroom_id: e.target.value })}
                  required
                >
                  <option value="">Choisir la classe</option>
                  {yearClassrooms.map((classroom) => (
                    <option key={classroom.id} value={classroom.id}>
                      {classroomLabel(classroom)}
                    </option>
                  ))}
                </select>
              </Field>
              {yearClassrooms.length === 0 ? (
                <p className="text-[11px] text-fanabe-clay">Créez d’abord une classe dans l’onglet Classes.</p>
              ) : (
                <p className="text-[11px] text-neutral-500">Les frais (droit d’inscription, écolage) dépendent de la classe et de l’année {yearLabel}.</p>
              )}
            </div>
          ) : null}

          {step === 2 ? (
            <div className="space-y-3">
              <div className="grid grid-cols-2 gap-1 rounded-md bg-black/5 p-0.5">
                <button type="button" className={modeTab(foyerMode === 'new')} onClick={() => setFoyerMode('new')}>
                  Nouveau foyer
                </button>
                <button type="button" className={modeTab(foyerMode === 'existing')} onClick={() => setFoyerMode('existing')}>
                  Foyer déjà inscrit
                </button>
              </div>
              {foyerMode === 'new' ? (
                <div className="space-y-2">
                  <Field label="Nom du foyer">
                    <input className={inputClass} value={label} onChange={(e) => setLabel(e.target.value)} />
                  </Field>
                  <div className="grid grid-cols-2 gap-2">
                    <Field label="Prénom du responsable">
                      <input className={inputClass} value={parent.first_name} onChange={(e) => setParent({ ...parent, first_name: e.target.value })} />
                    </Field>
                    <Field label="Nom">
                      <input className={inputClass} value={parent.last_name} onChange={(e) => setParent({ ...parent, last_name: e.target.value })} />
                    </Field>
                  </div>
                  <Field label="Téléphone">
                    <input className={inputClass} value={parent.phone} onChange={(e) => setParent({ ...parent, phone: e.target.value })} placeholder="034 00 000 00" />
                  </Field>
                  <Field label="Email (facultatif)">
                    <input className={inputClass} type="email" value={parent.email} onChange={(e) => setParent({ ...parent, email: e.target.value })} />
                  </Field>
                  <Field label="Lien avec l’élève">
                    <select className={inputClass} value={parent.relationship} onChange={(e) => setParent({ ...parent, relationship: e.target.value })}>
                      <option value="parent_of">Parent</option>
                      <option value="guardian_of">Tuteur</option>
                      <option value="financial_contact_for">Contact financier</option>
                    </select>
                  </Field>
                </div>
              ) : (
                <div className="space-y-2">
                  <Field label="Rechercher un foyer">
                    <input className={inputClass} value={familyQuery} onChange={(e) => setFamilyQuery(e.target.value)} placeholder="Nom de l’élève ou du parent" />
                  </Field>
                  <ul className="max-h-48 overflow-auto rounded-md border border-black/10">
                    {matchedFamilies.map((family) => {
                      const children = family.members.filter((member) => member.role_in_family === 'child')
                      const selected = family.id === familyId
                      return (
                        <li key={family.id}>
                          <button
                            type="button"
                            className={`flex w-full flex-col items-start px-3 py-2 text-left text-sm ${selected ? 'bg-fanabe-mist' : 'hover:bg-black/[0.03]'}`}
                            onClick={() => setFamilyId(family.id)}
                          >
                            <span className="font-medium">{family.label}</span>
                            <span className="text-[11px] text-neutral-500">
                              {children.map((member) => `${member.first_name} ${member.last_name}`).join(', ') || 'Aucun enfant'}
                            </span>
                          </button>
                        </li>
                      )
                    })}
                    {matchedFamilies.length === 0 ? (
                      <li className="px-3 py-3 text-xs text-neutral-500">Aucun foyer ne correspond.</li>
                    ) : null}
                  </ul>
                </div>
              )}
            </div>
          ) : null}

          {step === 3 ? (
            <dl className="space-y-2 text-sm">
              <div className="flex justify-between gap-3">
                <dt className="text-neutral-500">Élève</dt>
                <dd className="text-right font-medium">
                  {student.first_name} {student.last_name}
                  <span className="block text-xs font-normal text-neutral-500">
                    {formatDate(student.birth_date)} · {sexLabel(student.sex)}
                    {chosenClassroom ? ` · ${classroomLabel(chosenClassroom)}` : ''}
                  </span>
                </dd>
              </div>
              <div className="flex justify-between gap-3">
                <dt className="text-neutral-500">Foyer</dt>
                <dd className="text-right font-medium">
                  {foyerMode === 'existing' ? chosenFamily?.label : label || student.last_name}
                  {foyerMode === 'new' ? (
                    <span className="block text-xs font-normal text-neutral-500">
                      {relationshipLabel(parent.relationship)} · {parent.first_name} {parent.last_name}
                      {parent.phone ? ` · ${parent.phone}` : ''}
                    </span>
                  ) : (
                    <span className="block text-xs font-normal text-neutral-500">Fratrie d’un foyer déjà inscrit</span>
                  )}
                </dd>
              </div>
            </dl>
          ) : null}

          {step === 'done' ? (
            <div className="space-y-3">
              <p className="text-sm">
                <strong>{doneName}</strong> est inscrit
                {chosenClassroom ? ` en ${classroomLabel(chosenClassroom)}` : ''}
                {foyerMode === 'existing' ? ` au foyer ${chosenFamily?.label ?? ''}` : ` au foyer ${label || student.last_name}`}.
              </p>
              {invitation ? (
                <div>
                  <p className="text-xs text-neutral-600">Remettez ce code au responsable pour activer l’espace famille.</p>
                  <div className="mt-2 flex items-center justify-between gap-2 rounded-md bg-fanabe-mist px-3 py-2">
                    <strong className="font-mono text-sm">{invitation}</strong>
                    <button type="button" className={btnGhost} onClick={() => void copyText(invitation)}>
                      Copier
                    </button>
                  </div>
                </div>
              ) : (
                <p className="text-xs text-neutral-600">Les adultes déjà liés à ce foyer restent responsables.</p>
              )}
            </div>
          ) : null}
        </div>
        <div className="flex items-center justify-between gap-2 border-t border-black/5 px-4 py-2.5">
          {step === 1 ? (
            <button type="button" className={btnGhost} onClick={onClose}>
              Annuler
            </button>
          ) : step === 'done' ? (
            <span />
          ) : (
            <button type="button" className={btnGhost} onClick={() => setStep(step === 3 ? 2 : 1)} disabled={busy}>
              Retour
            </button>
          )}
          {step === 1 ? (
            <button type="button" className={btnPrimary} disabled={!studentReady} onClick={goFoyer}>
              Continuer
            </button>
          ) : null}
          {step === 2 ? (
            <button type="button" className={btnPrimary} disabled={!foyerReady} onClick={() => setStep(3)}>
              Continuer
            </button>
          ) : null}
          {step === 3 ? (
            <button type="button" className={btnPrimary} disabled={busy || !yearId} onClick={() => void submit()}>
              {busy ? 'Enregistrement…' : 'Inscrire'}
            </button>
          ) : null}
          {step === 'done' ? (
            <button type="button" className={btnPrimary} onClick={onClose}>
              Terminer
            </button>
          ) : null}
        </div>
      </div>
    </div>
  )
}

function starterFeeItems(startsOn?: string): Array<{ label: string; amount: string; due_on: string; category: string }> {
  const start = startsOn ? new Date(`${startsOn}T00:00:00`) : new Date('2026-09-01T00:00:00')
  const iso = (months: number, day: number) => {
    const date = new Date(start)
    date.setMonth(date.getMonth() + months)
    date.setDate(day)
    return date.toISOString().slice(0, 10)
  }
  return [
    { label: 'Droit d’inscription', amount: '20000', due_on: iso(0, 1), category: 'registration' },
    { label: 'Écolage 1er trimestre', amount: '50000', due_on: iso(0, 15), category: 'tuition' },
    { label: 'Écolage 2e trimestre', amount: '50000', due_on: iso(4, 15), category: 'tuition' },
    { label: 'Écolage 3e trimestre', amount: '50000', due_on: iso(7, 15), category: 'tuition' },
  ]
}

function FeeSettingsPanel({
  schoolId,
  auth,
  years,
  grades,
  currentYearId,
  schedules,
  busy,
  onBusy,
  onMessage,
  onRefresh,
}: {
  schoolId: string
  auth: { token: string }
  years: YearRow[]
  grades: Array<{ id: string; name: string }>
  currentYearId: string
  schedules: FeeScheduleRow[]
  busy: boolean
  onBusy: (value: boolean) => void
  onMessage: (value: string | null) => void
  onRefresh: () => Promise<void>
}) {
  const [yearId, setYearId] = useState(currentYearId)
  const [selectedId, setSelectedId] = useState<string | null>(null)
  const [creating, setCreating] = useState(false)
  const [name, setName] = useState('')
  const [gradeId, setGradeId] = useState('')
  const [items, setItems] = useState(starterFeeItems())
  const [sourceYearId, setSourceYearId] = useState('')
  const [adjustType, setAdjustType] = useState<'none' | 'amount' | 'percent'>('none')
  const [lineAdjustType, setLineAdjustType] = useState<'amount' | 'percent'>('percent')
  const [adjustValue, setAdjustValue] = useState('10')
  const [unlockReason, setUnlockReason] = useState('')

  const year = years.find((row) => row.id === yearId) ?? years[0]
  const yearSchedules = schedules.filter((row) => row.school_year_id === (year?.id ?? yearId))
  const selected = yearSchedules.find((row) => row.id === selectedId) ?? null
  const previousYears = years.filter((row) => row.id !== (year?.id ?? yearId))
  const locked = Boolean(selected?.locked)

  useEffect(() => {
    if (currentYearId && (yearId === '' || !years.some((row) => row.id === yearId))) {
      setYearId(currentYearId)
    }
  }, [currentYearId, years, yearId])

  useEffect(() => {
    if (creating) return
    const list = schedules.filter((row) => row.school_year_id === yearId)
    if (selectedId && list.some((row) => row.id === selectedId)) return
    setSelectedId(list[0]?.id ?? null)
  }, [yearId, schedules, creating, selectedId])

  const itemSignature = selected?.items.map((item) => `${item.id}:${item.amount}:${item.label}:${item.due_on}`).join('|')

  useEffect(() => {
    if (!selected || creating) return
    setName(selected.name)
    setGradeId(selected.grade_level_id ?? '')
    setItems(
      selected.items.map((item) => ({
        label: item.label,
        amount: String(item.amount),
        due_on: item.due_on,
        category: item.category,
      })),
    )
  }, [selected?.id, itemSignature, creating])

  function startCreate() {
    setCreating(true)
    setSelectedId(null)
    setName(`Barème ${year?.label ?? ''}`)
    setGradeId('')
    setItems(starterFeeItems(year?.starts_on))
  }

  async function run(action: () => Promise<void>, ok?: string) {
    onBusy(true)
    onMessage(null)
    try {
      await action()
      await onRefresh()
      if (ok) onMessage(ok)
    } catch (error) {
      onMessage(error instanceof Error ? error.message : 'Action impossible.')
    } finally {
      onBusy(false)
    }
  }

  function payloadItems() {
    return items
      .filter((item) => item.label.trim() !== '')
      .map((item) => ({
        label: item.label.trim(),
        amount: Number(item.amount),
        due_on: item.due_on,
        category: item.category,
      }))
  }

  return (
    <div className="grid gap-3 lg:grid-cols-[16rem_1fr]">
      <Panel className="p-3">
        <h2 className="text-sm font-semibold">Barèmes</h2>
        <p className="mt-1 text-[11px] text-neutral-500">Paramétrés à l’ouverture des inscriptions, puis verrouillés.</p>
        <select className={`${inputClass} mt-3`} value={year?.id ?? yearId} onChange={(e) => setYearId(e.target.value)}>
          {years.map((row) => (
            <option key={row.id} value={row.id}>
              {row.label}
              {row.is_current ? ' · en cours' : ''}
            </option>
          ))}
        </select>
        <ul className="mt-3 divide-y divide-black/5 text-sm">
          {yearSchedules.map((row) => (
            <li key={row.id}>
              <button
                type="button"
                className={`flex w-full items-center justify-between py-1.5 text-left ${
                  !creating && selectedId === row.id ? 'font-semibold text-fanabe-leaf' : ''
                }`}
                onClick={() => {
                  setCreating(false)
                  setSelectedId(row.id)
                }}
              >
                <span>{row.grade_level?.name ?? 'Toute l’école'}</span>
                <span className="text-[11px] text-neutral-500">{feeStatusLabel(row.status, row.locked)}</span>
              </button>
            </li>
          ))}
        </ul>
        {yearSchedules.length === 0 ? <p className="mt-3 text-xs text-neutral-500">Aucun barème pour {year?.label}.</p> : null}
        <button type="button" className={`${btnGhost} mt-3 w-full`} onClick={startCreate} disabled={busy}>
          Nouveau barème
        </button>
        {previousYears.length > 0 ? (
          <div className="mt-4 space-y-2 border-t border-black/5 pt-3">
            <p className="text-xs font-medium text-neutral-600">Reprendre une année</p>
            <select className={inputClass} value={sourceYearId} onChange={(e) => setSourceYearId(e.target.value)}>
              <option value="">Année source</option>
              {previousYears.map((row) => (
                <option key={row.id} value={row.id}>
                  {row.label}
                </option>
              ))}
            </select>
            <select className={inputClass} value={adjustType} onChange={(e) => setAdjustType(e.target.value as typeof adjustType)}>
              <option value="none">Sans changement</option>
              <option value="amount">Écart en Ariary</option>
              <option value="percent">Pourcentage</option>
            </select>
            {adjustType !== 'none' ? (
              <input
                className={inputClass}
                value={adjustValue}
                onChange={(e) => setAdjustValue(e.target.value)}
                placeholder={adjustType === 'percent' ? '+10 %' : '+5000 Ar'}
              />
            ) : null}
            <button
              type="button"
              className={btnBlock}
              disabled={busy || !sourceYearId || !year}
              onClick={() =>
                void run(async () => {
                  const body: Record<string, unknown> = {
                    source_year_id: sourceYearId,
                    target_year_id: year?.id,
                  }
                  if (adjustType === 'amount') {
                    body.adjustment_type = 'amount'
                    body.adjustment_amount = Number(adjustValue)
                  }
                  if (adjustType === 'percent') {
                    body.adjustment_type = 'percent'
                    body.adjustment_percent = Number(adjustValue)
                  }
                  const payload = await api<{ data: FeeScheduleRow[] }>(`/api/v1/schools/${schoolId}/fee-schedules/copy-year`, {
                    ...auth,
                    method: 'POST',
                    body: JSON.stringify(body),
                  })
                  setCreating(false)
                  setSelectedId(payload.data[0]?.id ?? null)
                }, 'Barèmes copiés. Vérifiez les montants avant validation.')
              }
            >
              Copier
            </button>
          </div>
        ) : null}
      </Panel>
      <Panel className="min-w-0 p-3">
        {creating || selected ? (
          <>
            <div className="flex flex-wrap items-start justify-between gap-2">
              <div>
                <h2 className="text-sm font-semibold">{creating ? 'Nouveau barème' : selected?.name}</h2>
                <p className="mt-0.5 text-xs text-neutral-500">
                  {year?.label} · {feeStatusLabel(selected?.status, selected?.locked)}
                  {selected?.copied_from_schedule_id ? ' · repris de l’année précédente' : ''}
                </p>
              </div>
              <p className="text-sm font-medium tabular-nums">
                {formatAr(items.reduce((sum, item) => sum + (Number(item.amount) || 0), 0))}
              </p>
            </div>
            {locked ? (
              <p className="mt-2 rounded-md bg-fanabe-mist px-3 py-2 text-xs text-neutral-700">
                Verrouillé après double validation. Toute modification exige une demande de support FANABE.
              </p>
            ) : null}
            <div className="mt-3 grid gap-2 sm:grid-cols-2">
              <Field label="Libellé">
                <input className={inputClass} value={name} onChange={(e) => setName(e.target.value)} disabled={locked} />
              </Field>
              <Field label="Niveau">
                <select className={inputClass} value={gradeId} onChange={(e) => setGradeId(e.target.value)} disabled={locked}>
                  <option value="">Toute l’école</option>
                  {grades.map((grade) => (
                    <option key={grade.id} value={grade.id}>
                      {grade.name}
                    </option>
                  ))}
                </select>
              </Field>
            </div>
            <div className="mt-3 overflow-auto">
              <table className="w-full text-sm">
                <thead className="bg-black/[0.03] text-left text-[11px] uppercase tracking-wide text-neutral-500">
                  <tr>
                    <th className="px-2 py-1.5 font-medium">Type</th>
                    <th className="px-2 py-1.5 font-medium">Libellé</th>
                    <th className="px-2 py-1.5 font-medium">Montant</th>
                    <th className="px-2 py-1.5 font-medium">Échéance</th>
                    <th className="px-2 py-1.5" />
                  </tr>
                </thead>
                <tbody>
                  {items.map((item, index) => (
                    <tr key={index} className="border-t border-black/5">
                      <td className="px-2 py-1">
                        <select
                          className={inputClass}
                          value={item.category}
                          disabled={locked}
                          onChange={(e) =>
                            setItems((prev) => prev.map((row, i) => (i === index ? { ...row, category: e.target.value } : row)))
                          }
                        >
                          <option value="registration">Droit d’inscription</option>
                          <option value="tuition">Écolage</option>
                          <option value="exam">Examen</option>
                          <option value="association">Cotisation APE</option>
                          <option value="other">Autre</option>
                        </select>
                      </td>
                      <td className="px-2 py-1">
                        <input
                          className={inputClass}
                          value={item.label}
                          disabled={locked}
                          onChange={(e) =>
                            setItems((prev) => prev.map((row, i) => (i === index ? { ...row, label: e.target.value } : row)))
                          }
                        />
                      </td>
                      <td className="px-2 py-1">
                        <input
                          className={inputClass}
                          type="number"
                          min={1}
                          step={1}
                          value={item.amount}
                          disabled={locked}
                          onChange={(e) =>
                            setItems((prev) => prev.map((row, i) => (i === index ? { ...row, amount: e.target.value } : row)))
                          }
                        />
                      </td>
                      <td className="px-2 py-1">
                        <input
                          className={inputClass}
                          type="date"
                          value={item.due_on}
                          disabled={locked}
                          onChange={(e) =>
                            setItems((prev) => prev.map((row, i) => (i === index ? { ...row, due_on: e.target.value } : row)))
                          }
                        />
                      </td>
                      <td className="px-2 py-1">
                        <button
                          type="button"
                          className={btnGhost}
                          disabled={locked || items.length <= 1}
                          onClick={() => setItems((prev) => prev.filter((_, i) => i !== index))}
                        >
                          Retirer
                        </button>
                      </td>
                    </tr>
                  ))}
                </tbody>
              </table>
            </div>
            {!locked ? (
              <button
                type="button"
                className={`${btnGhost} mt-2`}
                onClick={() =>
                  setItems((prev) => [...prev, { label: '', amount: '10000', due_on: year?.starts_on ?? '', category: 'other' }])
                }
              >
                Ajouter une ligne
              </button>
            ) : null}
            {!locked && selected && !creating ? (
              <div className="mt-3 flex flex-wrap items-end gap-2">
                <Field label="Ajuster toutes les lignes">
                  <select className={inputClass} value={lineAdjustType} onChange={(e) => setLineAdjustType(e.target.value as typeof lineAdjustType)}>
                    <option value="amount">Écart en Ariary</option>
                    <option value="percent">Pourcentage</option>
                  </select>
                </Field>
                <input className={`${inputClass} max-w-[8rem]`} value={adjustValue} onChange={(e) => setAdjustValue(e.target.value)} />
                <button
                  type="button"
                  className={btnGhost}
                  disabled={busy}
                  onClick={() =>
                    void run(async () => {
                      await api(`/api/v1/schools/${schoolId}/fee-schedules/${selected.id}/adjust`, {
                        ...auth,
                        method: 'POST',
                        body: JSON.stringify(
                          lineAdjustType === 'percent'
                            ? { adjustment_type: 'percent', adjustment_percent: Number(adjustValue) }
                            : { adjustment_type: 'amount', adjustment_amount: Number(adjustValue) },
                        ),
                      })
                    }, 'Montants ajustés. Vous pouvez encore corriger à la main.')
                  }
                >
                  Appliquer
                </button>
              </div>
            ) : null}
            <div className="mt-4 flex flex-wrap gap-2">
              {!locked ? (
                <button
                  type="button"
                  className={btnPrimary}
                  disabled={busy || name.trim() === '' || payloadItems().length === 0}
                  onClick={() =>
                    void run(async () => {
                      if (creating) {
                        const payload = await api<{ data: FeeScheduleRow }>(`/api/v1/schools/${schoolId}/fee-schedules`, {
                          ...auth,
                          method: 'POST',
                          body: JSON.stringify({
                            school_year_id: year?.id,
                            grade_level_id: gradeId || null,
                            name,
                            items: payloadItems(),
                          }),
                        })
                        setCreating(false)
                        setSelectedId(payload.data.id)
                      } else if (selected) {
                        await api(`/api/v1/schools/${schoolId}/fee-schedules/${selected.id}`, {
                          ...auth,
                          method: 'PATCH',
                          body: JSON.stringify({
                            name,
                            grade_level_id: gradeId || null,
                            items: payloadItems(),
                          }),
                        })
                      }
                    }, creating ? 'Brouillon enregistré.' : 'Barème mis à jour.')
                  }
                >
                  Enregistrer
                </button>
              ) : null}
              {selected && selected.status === 'draft' ? (
                <button
                  type="button"
                  className={btnGhost}
                  disabled={busy}
                  onClick={() =>
                    void run(async () => {
                      await api(`/api/v1/schools/${schoolId}/fee-schedules/${selected.id}/submit`, { ...auth, method: 'POST' })
                    }, 'Première validation enregistrée.')
                  }
                >
                  1re validation
                </button>
              ) : null}
              {selected && selected.status === 'pending_validation' ? (
                <>
                  <button
                    type="button"
                    className={btnPrimary}
                    disabled={busy}
                    onClick={() =>
                      void run(async () => {
                        await api(`/api/v1/schools/${schoolId}/fee-schedules/${selected.id}/confirm`, { ...auth, method: 'POST' })
                      }, 'Barème verrouillé pour l’année.')
                    }
                  >
                    2e validation
                  </button>
                  <button
                    type="button"
                    className={btnGhost}
                    disabled={busy}
                    onClick={() =>
                      void run(async () => {
                        await api(`/api/v1/schools/${schoolId}/fee-schedules/${selected.id}/reopen`, { ...auth, method: 'POST' })
                      }, 'Renvoyé en brouillon.')
                    }
                  >
                    Revenir au brouillon
                  </button>
                </>
              ) : null}
            </div>
            {locked ? (
              <form
                className="mt-4 space-y-2 border-t border-black/5 pt-3"
                onSubmit={(event) => {
                  event.preventDefault()
                  if (!selected) return
                  void run(async () => {
                    await api(`/api/v1/schools/${schoolId}/fee-schedules/${selected.id}/request-unlock`, {
                      ...auth,
                      method: 'POST',
                      body: JSON.stringify({ reason: unlockReason }),
                    })
                    setUnlockReason('')
                  }, 'Demande de support enregistrée. Le barème reste verrouillé.')
                }}
              >
                <Field label="Demande de support">
                  <input
                    className={inputClass}
                    value={unlockReason}
                    onChange={(e) => setUnlockReason(e.target.value)}
                    placeholder="Motif (erreur de saisie, décision du conseil…)"
                    required
                  />
                </Field>
                <button type="submit" className={btnGhost} disabled={busy || unlockReason.trim() === ''}>
                  Demander le support FANABE
                </button>
                {selected?.unlock_requested_at ? (
                  <p className="text-[11px] text-neutral-500">Demande déjà transmise. Le barème ne peut toujours pas être modifié.</p>
                ) : null}
              </form>
            ) : null}
          </>
        ) : (
          <p className="py-8 text-center text-sm text-neutral-500">Choisissez un barème ou créez-en un pour {year?.label}.</p>
        )}
      </Panel>
    </div>
  )
}

function ClassFilePanel({
  schoolId,
  auth,
  file,
  staff,
  terms,
  classrooms,
  busy,
  readOnly = false,
  canWriteGrades,
  onBusy,
  onMessage,
  onReload,
  onAssignClassroom,
}: {
  schoolId: string
  auth: { token: string }
  file: ClassFile
  staff: PersonMini[]
  terms: TermRow[]
  classrooms: ClassroomRow[]
  busy: boolean
  readOnly?: boolean
  canWriteGrades?: boolean
  onBusy: (value: boolean) => void
  onMessage: (value: string | null) => void
  onReload: () => Promise<void>
  onAssignClassroom?: (enrollmentId: string, classroomId: string) => Promise<void>
}) {
  const classroom = file.classroom
  const classroomId = classroom.id
  const [capacity, setCapacity] = useState(classroom.capacity ? String(classroom.capacity) : '')
  const [series, setSeries] = useState(classroom.series ?? '')
  const [teacherPerson, setTeacherPerson] = useState('')
  const [teacherSubject, setTeacherSubject] = useState('')
  const [slotWeekday, setSlotWeekday] = useState('1')
  const [slotStart, setSlotStart] = useState('07:30')
  const [slotEnd, setSlotEnd] = useState('08:25')
  const [slotSubject, setSlotSubject] = useState('')
  const [slotTeacher, setSlotTeacher] = useState('')
  const [slotRoom, setSlotRoom] = useState('')
  const [councilTerm, setCouncilTerm] = useState('')
  const [councilDate, setCouncilDate] = useState('')
  const [councilTitle, setCouncilTitle] = useState('')
  const [councilMinutes, setCouncilMinutes] = useState('')
  const [councilStatus, setCouncilStatus] = useState('scheduled')
  const [activityType, setActivityType] = useState('parent_meeting')
  const [activityTitle, setActivityTitle] = useState('')
  const [activityDate, setActivityDate] = useState('')
  const [activityLocation, setActivityLocation] = useState('')
  const [activityNotes, setActivityNotes] = useState('')
  const [subjects, setSubjects] = useState<Array<{ id: string; name: string }>>([])
  const [grades, setGrades] = useState<Array<{ id: string; enrollment_id: string; subject: string | null; value: number; assessed_on: string | null }>>([])
  const [gradeStudent, setGradeStudent] = useState('')
  const [gradeSubject, setGradeSubject] = useState('')
  const [gradeValue, setGradeValue] = useState('12')
  const [gradeDate, setGradeDate] = useState(() => new Date().toISOString().slice(0, 10))
  const [verifyUrl, setVerifyUrl] = useState<string | null>(null)
  const [gradeTick, setGradeTick] = useState(0)
  const [newSubject, setNewSubject] = useState('')
  const [certificates, setCertificates] = useState<
    Array<{
      id: string
      enrollment_id: string
      type_label: string
      public_reference: string
      status: string
      issued_at: string | null
      student_name: string
    }>
  >([])
  const [certTick, setCertTick] = useState(0)

  useEffect(() => {
    setCapacity(classroom.capacity ? String(classroom.capacity) : '')
    setSeries(classroom.series ?? '')
  }, [classroom.id, classroom.capacity, classroom.series])

  async function run(action: () => Promise<void>, ok?: string) {
    onBusy(true)
    onMessage(null)
    try {
      await action()
      await onReload()
      if (ok) onMessage(ok)
    } catch (error) {
      onMessage(error instanceof Error ? error.message : 'Action impossible.')
    } finally {
      onBusy(false)
    }
  }

  function lifeUrl(suffix: string): string {
    return `/api/v1/schools/${schoolId}/classrooms/${classroomId}${suffix}`
  }

  async function patchClassroom(body: Record<string, string | number | null>) {
    await run(async () => {
      await api(`/api/v1/schools/${schoolId}/classrooms/${classroomId}`, {
        ...auth,
        method: 'PATCH',
        body: JSON.stringify(body),
      })
    })
  }

  const addedTeacherIds = new Set(file.teachers.map((row) => row.person_id))
  const availableTeachers = staff.filter((person) => !addedTeacherIds.has(person.id))
  const stage = classroom.grade_level?.stage
  const cycleLabel = classroom.grade_level?.stage_label ?? (stage ? stageLabel(stage) : '')
  const unit = classroom.grade_level?.unit_label ?? unitLabel(stage)
  const canDelegate = showsDelegate(stage)
  const canCouncil = showsCouncil(stage)
  const canGrades = showsGrades(stage)
  const writeGrades = canWriteGrades ?? !readOnly
  const canSeries = stage === 'high'
  const pickup = file.pickup ?? []

  useEffect(() => {
    if (!canGrades) {
      setGrades([])
      setSubjects([])
      return
    }
    Promise.all([
      api<{ data: Array<{ id: string; name: string }> }>(`/api/v1/schools/${schoolId}/subjects`, auth),
      api<{ data: Array<{ id: string; enrollment_id: string; subject: string | null; value: number; assessed_on: string | null }> }>(
        `/api/v1/schools/${schoolId}/classrooms/${classroomId}/grades`,
        auth,
      ),
    ])
      .then(([subjectList, gradeList]) => {
        setSubjects(subjectList.data)
        setGrades(gradeList.data)
        setGradeSubject((prev) => prev || subjectList.data[0]?.id || '')
        setGradeStudent((prev) => prev || file.students[0]?.enrollment_id || '')
      })
      .catch((error: Error) => onMessage(error.message))
  }, [canGrades, classroomId, schoolId, auth.token, file.students.length, gradeTick])

  useEffect(() => {
    const enrollmentIds = new Set(file.students.map((row) => row.enrollment_id))
    api<{
      data: Array<{
        id: string
        enrollment_id: string
        type_label: string
        public_reference: string
        status: string
        issued_at: string | null
        student_name: string
      }>
    }>(`/api/v1/schools/${schoolId}/certificates`, auth)
      .then((payload) => {
        setCertificates(payload.data.filter((row) => enrollmentIds.has(row.enrollment_id)))
      })
      .catch(() => setCertificates([]))
  }, [classroomId, schoolId, auth.token, file.students.length, certTick])

  return (
    <div>
      <div className="px-3 py-2">
        <div className="flex flex-wrap items-start justify-between gap-2">
          <div>
            <h2 className="text-sm font-semibold">{classroom.name}</h2>
            <p className="text-xs text-neutral-500">
              {classroom.grade_level?.name ?? 'Niveau'}
              {cycleLabel ? ` · ${cycleLabel}` : ''}
              {canSeries && classroom.series ? ` · série ${classroom.series}` : ''}
              {classroom.main_teacher ? ` · ${personLabel(classroom.main_teacher)}` : ''}
            </p>
          </div>
          <p className="text-xs text-neutral-600">
            {file.headcount}
            {classroom.capacity ? ` / ${classroom.capacity}` : ''} élèves
          </p>
        </div>
        {readOnly ? (
          canDelegate ? (
            <p className="mt-2 text-xs text-neutral-600">
              Délégué {personLabel(classroom.delegate)} · Vice {personLabel(classroom.vice_delegate)}
            </p>
          ) : null
        ) : (
          <div className={`mt-2 grid gap-2 sm:grid-cols-2 ${canDelegate || canSeries ? 'lg:grid-cols-4' : ''}`}>
            <Field label="Titulaire">
              <select
                className={inputClass}
                value={classroom.main_teacher_person_id ?? ''}
                disabled={busy}
                onChange={(event) => {
                  void patchClassroom({ main_teacher_person_id: event.target.value || null })
                }}
              >
                <option value="">Sans titulaire</option>
                {staff.map((person) => (
                  <option key={person.id} value={person.id}>
                    {personLabel(person)}
                  </option>
                ))}
              </select>
            </Field>
            {canDelegate ? (
              <>
                <Field label="Délégué">
                  <select
                    className={inputClass}
                    value={classroom.delegate_person_id ?? ''}
                    disabled={busy}
                    onChange={(event) => {
                      void patchClassroom({ delegate_person_id: event.target.value || null })
                    }}
                  >
                    <option value="">Aucun</option>
                    {file.students.map((row) => (
                      <option key={row.person_id} value={row.person_id} disabled={row.person_id === classroom.vice_delegate_person_id}>
                        {row.person ? personLabel(row.person) : row.person_id}
                      </option>
                    ))}
                  </select>
                </Field>
                <Field label="Vice-délégué">
                  <select
                    className={inputClass}
                    value={classroom.vice_delegate_person_id ?? ''}
                    disabled={busy}
                    onChange={(event) => {
                      void patchClassroom({ vice_delegate_person_id: event.target.value || null })
                    }}
                  >
                    <option value="">Aucun</option>
                    {file.students.map((row) => (
                      <option key={row.person_id} value={row.person_id} disabled={row.person_id === classroom.delegate_person_id}>
                        {row.person ? personLabel(row.person) : row.person_id}
                      </option>
                    ))}
                  </select>
                </Field>
              </>
            ) : null}
            <Field label="Capacité">
              <form
                className="flex gap-1"
                onSubmit={(event) => {
                  event.preventDefault()
                  void patchClassroom({ capacity: capacity.trim() === '' ? null : Number(capacity) })
                }}
              >
                <input
                  className={inputClass}
                  type="number"
                  min={1}
                  value={capacity}
                  onChange={(e) => setCapacity(e.target.value)}
                  placeholder="—"
                />
                <button type="submit" className={btnGhost} disabled={busy}>
                  OK
                </button>
              </form>
            </Field>
            {canSeries ? (
              <Field label="Série">
                <form
                  className="flex gap-1"
                  onSubmit={(event) => {
                    event.preventDefault()
                    void patchClassroom({ series: series.trim() === '' ? null : series.trim() })
                  }}
                >
                  <input
                    className={inputClass}
                    value={series}
                    onChange={(e) => setSeries(e.target.value)}
                    placeholder="S, A, Technique…"
                    maxLength={32}
                  />
                  <button type="submit" className={btnGhost} disabled={busy}>
                    OK
                  </button>
                </form>
              </Field>
            ) : null}
          </div>
        )}
      </div>

      <ClassSection title={`Effectif · ${file.headcount}`}>
        {file.students.length === 0 ? (
          <p className="text-xs text-neutral-500">
            Aucun élève inscrit dans {unit === 'Groupe' ? 'ce groupe' : 'cette classe'}.
          </p>
        ) : (
          <table className="w-full text-sm">
            <thead className="text-left text-[11px] uppercase tracking-wide text-neutral-500">
              <tr>
                <th className="py-1 font-medium">N°</th>
                <th className="py-1 font-medium">Élève</th>
                {canDelegate ? <th className="py-1 font-medium">Office</th> : null}
                {readOnly || !onAssignClassroom ? null : <th className="py-1 font-medium">{unit}</th>}
              </tr>
            </thead>
            <tbody>
              {file.students.map((row) => (
                <tr key={row.enrollment_id} className="border-t border-black/5">
                  <td className="py-1.5 tabular-nums text-neutral-500">{row.student_number ?? '—'}</td>
                  <td className="py-1.5 font-medium">{row.person ? personLabel(row.person) : '—'}</td>
                  {canDelegate ? <td className="py-1.5 text-xs text-neutral-600">{officeLabel(row.office) || '—'}</td> : null}
                  {readOnly || !onAssignClassroom ? null : (
                    <td className="py-1.5">
                      <select
                        className={inputClass}
                        value={classroomId}
                        disabled={busy}
                        onChange={(event) => {
                          if (event.target.value) void onAssignClassroom(row.enrollment_id, event.target.value)
                        }}
                      >
                        {classrooms.map((item) => (
                          <option key={item.id} value={item.id}>
                            {item.name}
                          </option>
                        ))}
                      </select>
                    </td>
                  )}
                </tr>
              ))}
            </tbody>
          </table>
        )}
      </ClassSection>

      {canGrades ? (
        <ClassSection title="Notes">
          {grades.length === 0 ? <p className="text-xs text-neutral-500">Aucune note pour cette classe.</p> : null}
          <ul className="space-y-1 text-sm">
            {grades.map((row) => {
              const student = file.students.find((item) => item.enrollment_id === row.enrollment_id)
              return (
                <li key={row.id} className="flex justify-between gap-2 border-t border-black/5 pt-1.5 first:border-t-0 first:pt-0">
                  <span>
                    {student?.person ? personLabel(student.person) : 'Élève'}
                    <span className="ml-2 text-xs text-neutral-500">{row.subject}</span>
                  </span>
                  <span className="tabular-nums">
                    {row.value}
                    <span className="ml-2 text-xs text-neutral-500">{row.assessed_on ? formatDate(row.assessed_on) : ''}</span>
                  </span>
                </li>
              )
            })}
          </ul>
          {writeGrades ? (
            <>
              <form
                className="mt-2 flex gap-1"
                onSubmit={(event) => {
                  event.preventDefault()
                  const name = newSubject.trim()
                  if (!name) return
                  void run(async () => {
                    const created = await api<{ data: { id: string; name: string } }>(`/api/v1/schools/${schoolId}/subjects`, {
                      ...auth,
                      method: 'POST',
                      body: JSON.stringify({ name }),
                    })
                    setNewSubject('')
                    setGradeSubject(created.data.id)
                    setGradeTick((n) => n + 1)
                  }, 'Matière ajoutée.')
                }}
              >
                <input
                  className={inputClass}
                  value={newSubject}
                  onChange={(e) => setNewSubject(e.target.value)}
                  placeholder="Nouvelle matière"
                />
                <button type="submit" className={btnGhost} disabled={busy || newSubject.trim() === ''}>
                  Ajouter
                </button>
              </form>
              <form
                className="mt-2 grid gap-2 sm:grid-cols-4"
                onSubmit={(event) => {
                  event.preventDefault()
                  void run(async () => {
                    await api(`/api/v1/schools/${schoolId}/classrooms/${classroomId}/grades`, {
                      ...auth,
                      method: 'POST',
                      body: JSON.stringify({
                        enrollment_id: gradeStudent,
                        subject_id: gradeSubject,
                        value: Number(gradeValue),
                        assessed_on: gradeDate,
                      }),
                    })
                    setGradeTick((n) => n + 1)
                  }, 'Note enregistrée.')
                }}
              >
                <select className={inputClass} value={gradeStudent} onChange={(e) => setGradeStudent(e.target.value)} required>
                  {file.students.map((row) => (
                    <option key={row.enrollment_id} value={row.enrollment_id}>
                      {row.person ? personLabel(row.person) : row.enrollment_id}
                    </option>
                  ))}
                </select>
                <select className={inputClass} value={gradeSubject} onChange={(e) => setGradeSubject(e.target.value)} required>
                  {subjects.length === 0 ? <option value="">Aucune matière</option> : null}
                  {subjects.map((subject) => (
                    <option key={subject.id} value={subject.id}>
                      {subject.name}
                    </option>
                  ))}
                </select>
                <input className={inputClass} type="number" min={0} step="0.5" value={gradeValue} onChange={(e) => setGradeValue(e.target.value)} required />
                <input className={inputClass} type="date" value={gradeDate} onChange={(e) => setGradeDate(e.target.value)} required />
                <button type="submit" className={`${btnGhost} sm:col-span-4`} disabled={busy || !gradeStudent || !gradeSubject}>
                  Enregistrer la note
                </button>
              </form>
            </>
          ) : null}
        </ClassSection>
      ) : null}

      <ClassSection title="Documents">
        <p className="text-xs text-neutral-500">
          Certificat de scolarité avec lien de vérification. FANABE n’est pas une signature qualifiée.
        </p>
        {verifyUrl ? (
          <p className="mt-2 break-all text-xs">
            Lien :{' '}
            <a className="text-fanabe-leaf underline" href={verifyUrl}>
              {verifyUrl}
            </a>
          </p>
        ) : null}
        {certificates.length === 0 ? (
          <p className="mt-2 text-xs text-neutral-500">Aucun document émis pour cette classe.</p>
        ) : (
          <ul className="mt-2 space-y-1 text-sm">
            {certificates.map((row) => (
              <li key={row.id} className="flex justify-between gap-2 border-t border-black/5 pt-1.5 first:border-t-0 first:pt-0">
                <span>
                  {row.student_name}
                  <span className="ml-2 text-xs text-neutral-500">
                    {row.type_label} · {row.public_reference}
                  </span>
                </span>
                <span className="text-xs text-neutral-500">{certificateStatusLabel(row.status)}</span>
              </li>
            ))}
          </ul>
        )}
        {readOnly ? null : (
          <ul className="mt-2 space-y-1 text-sm">
            {file.students.map((row) => (
              <li key={row.enrollment_id} className="flex items-center justify-between gap-2 border-t border-black/5 py-1 first:border-t-0">
                <span>{row.person ? personLabel(row.person) : 'Élève'}</span>
                <button
                  type="button"
                  className={btnGhost}
                  disabled={busy}
                  onClick={() =>
                    void run(async () => {
                      const payload = await api<{ data: { verify_url?: string } }>(
                        `/api/v1/schools/${schoolId}/enrollments/${row.enrollment_id}/certificates`,
                        { ...auth, method: 'POST', body: JSON.stringify({}) },
                      )
                      setVerifyUrl(payload.data.verify_url ?? null)
                      setCertTick((n) => n + 1)
                    }, 'Certificat émis.')
                  }
                >
                  Émettre
                </button>
              </li>
            ))}
          </ul>
        )}
      </ClassSection>

      {stage === 'preschool' ? (
        <ClassSection title="Récupération">
          {pickup.length === 0 ? (
            <p className="text-xs text-neutral-500">
              Aucun enfant dans ce groupe pour l’instant. Les parents, tuteurs et personnes autorisées du foyer
              apparaîtront ici.
            </p>
          ) : (
            <ul className="space-y-2 text-sm">
              {pickup.map((row, index) => (
                <li key={row.student?.id ?? String(index)} className="border-t border-black/5 pt-2 first:border-t-0 first:pt-0">
                  <p className="font-medium">{row.student ? personLabel(row.student) : 'Élève'}</p>
                  {row.adults.length === 0 ? (
                    <p className="text-xs text-neutral-500">Personne indiquée pour la récupération.</p>
                  ) : (
                    <p className="text-xs text-neutral-600">
                      {row.adults
                        .map((adult) => `${personLabel(adult.person)}${adult.via ? ` · ${pickupViaLabel(adult.via)}` : ''}`)
                        .join(' · ')}
                    </p>
                  )}
                </li>
              ))}
            </ul>
          )}
        </ClassSection>
      ) : null}

      <ClassSection title="Enseignants">
        {file.teachers.length === 0 ? <p className="text-xs text-neutral-500">Aucun enseignant attribué.</p> : null}
        <ul className="space-y-1 text-sm">
          {file.teachers.map((row) => (
            <li key={row.id} className="flex items-center justify-between gap-2">
              <span>
                {personLabel(row.person)}
                {row.subject ? <span className="text-neutral-500"> · {row.subject}</span> : null}
                {row.is_main ? <span className="ml-1 text-[11px] text-fanabe-leaf">titulaire</span> : null}
              </span>
              {readOnly || row.is_main ? null : (
                <button
                  type="button"
                  className={btnGhost}
                  disabled={busy}
                  onClick={() =>
                    void run(async () => {
                      await api(lifeUrl(`/teachers/${row.person_id}`), { ...auth, method: 'DELETE' })
                    })
                  }
                >
                  Retirer
                </button>
              )}
            </li>
          ))}
        </ul>
        {readOnly ? null : (
          <form
            className="mt-2 grid gap-2 sm:grid-cols-[1fr_8rem_auto]"
            onSubmit={(event) => {
              event.preventDefault()
              if (!teacherPerson) return
              void run(async () => {
                await api(lifeUrl('/teachers'), {
                  ...auth,
                  method: 'POST',
                  body: JSON.stringify({ person_id: teacherPerson, subject: teacherSubject || null }),
                })
                setTeacherPerson('')
                setTeacherSubject('')
              }, 'Enseignant ajouté.')
            }}
          >
            <select className={inputClass} value={teacherPerson} onChange={(e) => setTeacherPerson(e.target.value)} required>
              <option value="">Personnel</option>
              {availableTeachers.map((person) => (
                <option key={person.id} value={person.id}>
                  {personLabel(person)}
                </option>
              ))}
            </select>
            <input className={inputClass} value={teacherSubject} onChange={(e) => setTeacherSubject(e.target.value)} placeholder="Matière" />
            <button type="submit" className={btnGhost} disabled={busy || !teacherPerson}>
              Ajouter
            </button>
          </form>
        )}
      </ClassSection>

      <ClassSection title="Emploi du temps">
        {file.timetable.length === 0 ? <p className="text-xs text-neutral-500">Aucun créneau.</p> : null}
        <table className="w-full text-sm">
          <tbody>
            {file.timetable.map((slot) => (
              <tr key={slot.id} className="border-t border-black/5">
                <td className="py-1.5 text-xs font-medium text-neutral-500">{weekdayLabel(slot.weekday)}</td>
                <td className="py-1.5 tabular-nums text-xs">
                  {slot.starts_at}–{slot.ends_at}
                </td>
                <td className="py-1.5 font-medium">{slot.subject}</td>
                <td className="py-1.5 text-neutral-600">{personLabel(slot.teacher)}</td>
                <td className="py-1.5 text-xs text-neutral-500">{slot.room ?? ''}</td>
                {readOnly ? null : (
                  <td className="py-1.5 text-right">
                    <button
                      type="button"
                      className={btnGhost}
                      disabled={busy}
                      onClick={() =>
                        void run(async () => {
                          await api(lifeUrl(`/timetable/${slot.id}`), { ...auth, method: 'DELETE' })
                        })
                      }
                    >
                      ×
                    </button>
                  </td>
                )}
              </tr>
            ))}
          </tbody>
        </table>
        {readOnly ? null : (
          <form
            className="mt-2 grid gap-2 sm:grid-cols-3 lg:grid-cols-7"
            onSubmit={(event) => {
              event.preventDefault()
              void run(async () => {
                await api(lifeUrl('/timetable'), {
                  ...auth,
                  method: 'POST',
                  body: JSON.stringify({
                    weekday: Number(slotWeekday),
                    starts_at: slotStart.slice(0, 5),
                    ends_at: slotEnd.slice(0, 5),
                    subject: slotSubject,
                    teacher_person_id: slotTeacher || null,
                    room: slotRoom || null,
                  }),
                })
                setSlotSubject('')
                setSlotRoom('')
              }, 'Créneau ajouté.')
            }}
          >
            <select className={inputClass} value={slotWeekday} onChange={(e) => setSlotWeekday(e.target.value)}>
              {WEEKDAY_LABELS.map((label, index) => (
                <option key={label} value={String(index + 1)}>
                  {label}
                </option>
              ))}
            </select>
            <input className={inputClass} type="time" value={slotStart} onChange={(e) => setSlotStart(e.target.value)} required />
            <input className={inputClass} type="time" value={slotEnd} onChange={(e) => setSlotEnd(e.target.value)} required />
            <input className={inputClass} value={slotSubject} onChange={(e) => setSlotSubject(e.target.value)} placeholder="Matière" required />
            <select className={inputClass} value={slotTeacher} onChange={(e) => setSlotTeacher(e.target.value)}>
              <option value="">Enseignant</option>
              {staff.map((person) => (
                <option key={person.id} value={person.id}>
                  {personLabel(person)}
                </option>
              ))}
            </select>
            <input className={inputClass} value={slotRoom} onChange={(e) => setSlotRoom(e.target.value)} placeholder="Salle" />
            <button type="submit" className={btnGhost} disabled={busy}>
              Ajouter
            </button>
          </form>
        )}
      </ClassSection>

      {canCouncil ? (
      <ClassSection title="Conseil de classe">
        {file.councils.length === 0 ? <p className="text-xs text-neutral-500">Aucun conseil enregistré.</p> : null}
        <ul className="space-y-2 text-sm">
          {file.councils.map((row) => (
            <li key={row.id} className="border-t border-black/5 pt-2 first:border-t-0 first:pt-0">
              <p className="font-medium">
                {row.title}
                <span className="ml-2 text-xs font-normal text-neutral-500">
                  {formatDate(row.held_on)} · {row.term?.label ?? 'Trimestre'} · {councilStatusLabel(row.status)}
                </span>
              </p>
              {row.minutes ? <p className="mt-0.5 text-xs text-neutral-600">{row.minutes}</p> : null}
            </li>
          ))}
        </ul>
        {readOnly ? null : (
          <form
            className="mt-2 space-y-2"
            onSubmit={(event) => {
              event.preventDefault()
              void run(async () => {
                await api(lifeUrl('/councils'), {
                  ...auth,
                  method: 'POST',
                  body: JSON.stringify({
                    academic_term_id: councilTerm || null,
                    held_on: councilDate,
                    title: councilTitle,
                    minutes: councilMinutes || null,
                    status: councilStatus,
                  }),
                })
                setCouncilTitle('')
                setCouncilMinutes('')
              }, 'Conseil enregistré.')
            }}
          >
            <div className="grid gap-2 sm:grid-cols-4">
              <select className={inputClass} value={councilTerm} onChange={(e) => setCouncilTerm(e.target.value)}>
                <option value="">Trimestre</option>
                {terms.map((term) => (
                  <option key={term.id} value={term.id}>
                    {term.label}
                  </option>
                ))}
              </select>
              <input className={inputClass} type="date" value={councilDate} onChange={(e) => setCouncilDate(e.target.value)} required />
              <input className={inputClass} value={councilTitle} onChange={(e) => setCouncilTitle(e.target.value)} placeholder="Titre" required />
              <select className={inputClass} value={councilStatus} onChange={(e) => setCouncilStatus(e.target.value)}>
                <option value="scheduled">Prévu</option>
                <option value="held">Tenu</option>
              </select>
            </div>
            <textarea
              className={`${inputClass} h-16 py-1.5`}
              value={councilMinutes}
              onChange={(e) => setCouncilMinutes(e.target.value)}
              placeholder="Compte rendu (sans notes)"
            />
            <button type="submit" className={btnGhost} disabled={busy}>
              Enregistrer le conseil
            </button>
          </form>
        )}
      </ClassSection>
      ) : null}

      <ClassSection title="Activités">
        {file.activities.length === 0 ? <p className="text-xs text-neutral-500">Aucune activité.</p> : null}
        <ul className="space-y-2 text-sm">
          {file.activities.map((row) => (
            <li key={row.id} className="flex items-start justify-between gap-2 border-t border-black/5 pt-2 first:border-t-0 first:pt-0">
              <div>
                <p className="font-medium">
                  {row.title}
                  <span className="ml-2 text-xs font-normal text-neutral-500">
                    {activityTypeLabel(row.type)} · {formatDate(row.held_on)}
                    {row.location ? ` · ${row.location}` : ''}
                  </span>
                </p>
                {row.notes ? <p className="mt-0.5 text-xs text-neutral-600">{row.notes}</p> : null}
              </div>
              {readOnly ? null : (
                <button
                  type="button"
                  className={btnGhost}
                  disabled={busy}
                  onClick={() =>
                    void run(async () => {
                      await api(lifeUrl(`/activities/${row.id}`), { ...auth, method: 'DELETE' })
                    })
                  }
                >
                  ×
                </button>
              )}
            </li>
          ))}
        </ul>
        {readOnly ? null : (
          <form
            className="mt-2 space-y-2"
            onSubmit={(event) => {
              event.preventDefault()
              void run(async () => {
                await api(lifeUrl('/activities'), {
                  ...auth,
                  method: 'POST',
                  body: JSON.stringify({
                    type: activityType,
                    title: activityTitle,
                    held_on: activityDate,
                    location: activityLocation || null,
                    notes: activityNotes || null,
                  }),
                })
                setActivityTitle('')
                setActivityLocation('')
                setActivityNotes('')
              }, 'Activité ajoutée.')
            }}
          >
            <div className="grid gap-2 sm:grid-cols-4">
              <select className={inputClass} value={activityType} onChange={(e) => setActivityType(e.target.value)}>
                <option value="parent_meeting">Réunion parents</option>
                <option value="outing">Sortie</option>
                <option value="celebration">Fête</option>
                <option value="other">Autre</option>
              </select>
              <input className={inputClass} value={activityTitle} onChange={(e) => setActivityTitle(e.target.value)} placeholder="Titre" required />
              <input className={inputClass} type="date" value={activityDate} onChange={(e) => setActivityDate(e.target.value)} required />
              <input className={inputClass} value={activityLocation} onChange={(e) => setActivityLocation(e.target.value)} placeholder="Lieu" />
            </div>
            <input className={inputClass} value={activityNotes} onChange={(e) => setActivityNotes(e.target.value)} placeholder="Notes" />
            <button type="submit" className={btnGhost} disabled={busy}>
              Ajouter l’activité
            </button>
          </form>
        )}
      </ClassSection>
    </div>
  )
}

function ExpensesPanel({
  schoolId,
  auth,
  years,
  currentYearId,
  busy,
  onBusy,
  onMessage,
}: {
  schoolId: string
  auth: { token: string }
  years: YearRow[]
  currentYearId: string
  busy: boolean
  onBusy: (value: boolean) => void
  onMessage: (value: string | null) => void
}) {
  const [yearId, setYearId] = useState(currentYearId)
  const [rows, setRows] = useState<ExpenseRow[]>([])
  const [total, setTotal] = useState(0)
  const [kind, setKind] = useState('purchase')
  const [category, setCategory] = useState('supplies')
  const [label, setLabel] = useState('')
  const [amount, setAmount] = useState('')
  const [spentOn, setSpentOn] = useState(() => new Date().toISOString().slice(0, 10))
  const [vendor, setVendor] = useState('')
  const [notes, setNotes] = useState('')

  useEffect(() => {
    if (currentYearId && (yearId === '' || !years.some((row) => row.id === yearId))) {
      setYearId(currentYearId)
    }
  }, [currentYearId, years, yearId])

  async function refresh() {
    if (!yearId) return
    const payload = await api<{ data: ExpenseRow[]; total_amount: number }>(
      `/api/v1/schools/${schoolId}/expenses?school_year_id=${yearId}`,
      auth,
    )
    setRows(payload.data)
    setTotal(payload.total_amount)
  }

  useEffect(() => {
    refresh().catch((error: Error) => onMessage(error.message))
  }, [yearId, schoolId, auth.token])

  async function submit(event: FormEvent) {
    event.preventDefault()
    onBusy(true)
    onMessage(null)
    try {
      await api(`/api/v1/schools/${schoolId}/expenses`, {
        ...auth,
        method: 'POST',
        body: JSON.stringify({
          school_year_id: yearId,
          kind,
          category,
          label,
          amount: Number(amount),
          spent_on: spentOn,
          vendor: vendor || null,
          notes: notes || null,
        }),
      })
      setLabel('')
      setAmount('')
      setVendor('')
      setNotes('')
      await refresh()
      onMessage('Achat enregistré.')
    } catch (error) {
      onMessage(error instanceof Error ? error.message : 'Achat impossible à enregistrer.')
    } finally {
      onBusy(false)
    }
  }

  return (
    <div className="grid gap-3 lg:grid-cols-[16rem_1fr]">
      <Panel className="p-3">
        <h2 className="text-sm font-semibold">Achats et dépenses</h2>
        <p className="mt-1 text-[11px] text-neutral-500">Registre de l’école, en Ariary. Pas de plan comptable.</p>
        <select className={`${inputClass} mt-3`} value={yearId} onChange={(e) => setYearId(e.target.value)}>
          {years.map((row) => (
            <option key={row.id} value={row.id}>
              {row.label}
              {row.is_current ? ' · en cours' : ''}
            </option>
          ))}
        </select>
        <p className="mt-3 text-sm font-semibold tabular-nums">{formatAr(total)}</p>
        <p className="text-[11px] text-neutral-500">{rows.length} ligne(s)</p>
      </Panel>
      <Panel className="min-w-0 p-3">
        <form onSubmit={submit} className="grid gap-2 sm:grid-cols-2 lg:grid-cols-3">
          <select className={inputClass} value={kind} onChange={(e) => setKind(e.target.value)}>
            <option value="purchase">Achat</option>
            <option value="expense">Dépense</option>
          </select>
          <select className={inputClass} value={category} onChange={(e) => setCategory(e.target.value)}>
            <option value="supplies">Fournitures</option>
            <option value="maintenance">Entretien</option>
            <option value="utilities">Charges</option>
            <option value="transport">Transport</option>
            <option value="food">Alimentation</option>
            <option value="other">Autre</option>
          </select>
          <input className={inputClass} type="date" value={spentOn} onChange={(e) => setSpentOn(e.target.value)} required />
          <input className={inputClass} value={label} onChange={(e) => setLabel(e.target.value)} placeholder="Libellé" required />
          <input
            className={inputClass}
            type="number"
            min={1}
            step={1}
            value={amount}
            onChange={(e) => setAmount(e.target.value)}
            placeholder="Montant Ar"
            required
          />
          <input className={inputClass} value={vendor} onChange={(e) => setVendor(e.target.value)} placeholder="Fournisseur" />
          <input className={`${inputClass} sm:col-span-2`} value={notes} onChange={(e) => setNotes(e.target.value)} placeholder="Notes" />
          <button type="submit" className={btnPrimary} disabled={busy || !yearId}>
            Enregistrer
          </button>
        </form>
        <table className="mt-3 w-full text-sm">
          <thead className="bg-black/[0.03] text-left text-[11px] uppercase tracking-wide text-neutral-500">
            <tr>
              <th className="px-2 py-1.5 font-medium">Date</th>
              <th className="px-2 py-1.5 font-medium">Libellé</th>
              <th className="px-2 py-1.5 font-medium">Catégorie</th>
              <th className="px-2 py-1.5 text-right font-medium">Montant</th>
            </tr>
          </thead>
          <tbody>
            {rows.map((row) => (
              <tr key={row.id} className="border-t border-black/5">
                <td className="px-2 py-1.5 text-xs text-neutral-500">{formatDate(row.spent_on)}</td>
                <td className="px-2 py-1.5">
                  <span className="font-medium">{row.label}</span>
                  <span className="ml-2 text-[11px] text-neutral-500">
                    {expenseKindLabel(row.kind)}
                    {row.vendor ? ` · ${row.vendor}` : ''}
                  </span>
                </td>
                <td className="px-2 py-1.5 text-neutral-600">{expenseCategoryLabel(row.category)}</td>
                <td className="px-2 py-1.5 text-right tabular-nums">{formatAr(row.amount)}</td>
              </tr>
            ))}
          </tbody>
        </table>
        {rows.length === 0 ? <p className="px-2 py-6 text-center text-sm text-neutral-500">Aucun achat pour cette année.</p> : null}
      </Panel>
    </div>
  )
}

function emptyKitLine() {
  return {
    label: '',
    quantity: '1',
    ecoBrand: '',
    ecoPrice: '',
    standardBrand: '',
    standardPrice: '',
    luxeBrand: '',
    luxePrice: '',
  }
}

function linesFromDefinition(row: KitDefinitionRow | undefined) {
  if (!row?.needs?.length) {
    return [emptyKitLine()]
  }
  return row.needs.map((need) => {
    const eco = kitOffer(need, 'eco')
    const standard = kitOffer(need, 'standard')
    const luxe = kitOffer(need, 'premium')
    return {
      label: need.label,
      quantity: String(need.quantity),
      ecoBrand: eco?.brand ?? '',
      ecoPrice: eco ? String(eco.unit_amount) : '',
      standardBrand: standard?.brand ?? '',
      standardPrice: standard ? String(standard.unit_amount) : '',
      luxeBrand: luxe?.brand ?? '',
      luxePrice: luxe ? String(luxe.unit_amount) : '',
    }
  })
}

function KitsPanel({
  schoolId,
  auth,
  yearId,
  years = [],
  grades,
  busy,
  onBusy,
  onMessage,
  canManageOrders = true,
  lockedGradeId,
}: {
  schoolId: string
  auth: { token: string }
  yearId: string
  years?: YearRow[]
  grades: GradeRow[]
  busy: boolean
  onBusy: (value: boolean) => void
  onMessage: (value: string | null) => void
  canManageOrders?: boolean
  lockedGradeId?: string
}) {
  const [catalog, setCatalog] = useState<KitDefinitionRow[]>([])
  const [orders, setOrders] = useState<KitOrderRow[]>([])
  const [gradeId, setGradeId] = useState(lockedGradeId ?? '')
  const [priceSource, setPriceSource] = useState('supplier')
  const [supplier, setSupplier] = useState('Librairie Analakely')
  const [lines, setLines] = useState([emptyKitLine()])
  const previousYears = years.filter((row) => row.id !== yearId)

  const selected = catalog.find((row) => row.grade_level_id === gradeId)

  async function refresh() {
    const defs = await api<{ data: KitDefinitionRow[] }>(`/api/v1/schools/${schoolId}/kit-definitions`, auth)
    setCatalog(defs.data)
    if (canManageOrders) {
      try {
        const list = await api<{ data: KitOrderRow[] }>(`/api/v1/schools/${schoolId}/kit-orders`, auth)
        setOrders(list.data)
      } catch {
        setOrders([])
      }
    }
    setGradeId((prev) => lockedGradeId || prev || grades[0]?.id || '')
  }

  useEffect(() => {
    refresh().catch((error: Error) => onMessage(error.message))
  }, [schoolId, auth.token, lockedGradeId])

  useEffect(() => {
    const row = catalog.find((item) => item.grade_level_id === gradeId)
    if (!row) {
      setLines([emptyKitLine()])
      setPriceSource('supplier')
      return
    }
    setLines(linesFromDefinition(row))
    setPriceSource(row.price_source ?? 'supplier')
    const name = row.packs[0]?.supplier?.name
    if (name) setSupplier(name)
  }, [gradeId, catalog])

  function updateLine(index: number, patch: Partial<ReturnType<typeof emptyKitLine>>) {
    setLines((current) => current.map((line, i) => (i === index ? { ...line, ...patch } : line)))
  }

  async function saveList(event: FormEvent) {
    event.preventDefault()
    const filled = lines.filter((line) => line.label.trim() !== '')
    if (filled.length === 0 || !gradeId) return
    onBusy(true)
    onMessage(null)
    try {
      await api(`/api/v1/schools/${schoolId}/kit-definitions`, {
        ...auth,
        method: 'POST',
        body: JSON.stringify({
          school_year_id: yearId,
          grade_level_id: gradeId,
          price_source: priceSource,
          supplier_name: priceSource === 'purchasing' ? 'Service achat' : supplier,
          commission_rate_bps: 250,
          needs: filled.map((line) => ({
            label: line.label.trim(),
            quantity: Number(line.quantity) || 1,
            offers: [
              { tier: 'eco', brand: line.ecoBrand.trim() || null, unit_amount: Number(line.ecoPrice) || 0 },
              { tier: 'standard', brand: line.standardBrand.trim() || null, unit_amount: Number(line.standardPrice) || 0 },
              { tier: 'luxe', brand: line.luxeBrand.trim() || null, unit_amount: Number(line.luxePrice) || 0 },
            ].filter((offer) => offer.unit_amount > 0),
          })),
        }),
      })
      onMessage('Liste de fournitures publiée. Les parents commandent chez le partenaire ou fournissent eux-mêmes.')
      await refresh()
    } catch (error) {
      onMessage(error instanceof Error ? error.message : 'Liste impossible à enregistrer.')
    } finally {
      onBusy(false)
    }
  }

  async function copyPrevious() {
    const from = previousYears[0]
    if (!from || !yearId) return
    onBusy(true)
    onMessage(null)
    try {
      await api(`/api/v1/schools/${schoolId}/kit-definitions/copy-year`, {
        ...auth,
        method: 'POST',
        body: JSON.stringify({ from_year_id: from.id, to_year_id: yearId }),
      })
      onMessage(`Liste reprise de ${from.label}. Ajustez marques et prix si besoin.`)
      await refresh()
    } catch (error) {
      onMessage(error instanceof Error ? error.message : 'Reprise impossible.')
    } finally {
      onBusy(false)
    }
  }

  async function confirmOrder(id: string) {
    onBusy(true)
    onMessage(null)
    try {
      await api(`/api/v1/schools/${schoolId}/kit-orders/${id}`, {
        ...auth,
        method: 'PATCH',
        body: JSON.stringify({ status: 'confirmed' }),
      })
      await refresh()
    } catch (error) {
      onMessage(error instanceof Error ? error.message : 'Commande impossible à confirmer.')
    } finally {
      onBusy(false)
    }
  }

  return (
    <div className="space-y-3">
      <Panel className="p-3">
        <h2 className="text-sm font-semibold">Fournitures de l’année</h2>
        <p className="mt-1 text-xs text-neutral-500">
          Liste par niveau, publiée à l’inscription. Trois gammes (éco, standard, luxe) : marque et prix unitaires.
          Le parent commande chez le partenaire ou fournit lui-même. FANABE n’encaisse pas.
        </p>
        <form onSubmit={saveList} className="mt-3 space-y-2">
          <div className="grid gap-2 sm:grid-cols-2">
            <select
              className={inputClass}
              value={gradeId}
              disabled={Boolean(lockedGradeId)}
              onChange={(e) => setGradeId(e.target.value)}
            >
              {grades.map((grade) => (
                <option key={grade.id} value={grade.id}>
                  {grade.name}
                </option>
              ))}
            </select>
            <select className={inputClass} value={priceSource} onChange={(e) => setPriceSource(e.target.value)}>
              <option value="supplier">Prix / marques : fournisseur</option>
              <option value="purchasing">Prix / marques : service achat</option>
            </select>
            {priceSource === 'supplier' ? (
              <input className={inputClass} value={supplier} onChange={(e) => setSupplier(e.target.value)} placeholder="Fournisseur partenaire" required />
            ) : (
              <p className="self-center text-xs text-neutral-500">Service achat de l’école.</p>
            )}
            {canManageOrders && previousYears.length > 0 ? (
              <button type="button" className={btnGhost} disabled={busy} onClick={() => void copyPrevious()}>
                Reprendre {previousYears[0].label}
              </button>
            ) : null}
          </div>
          <div className="overflow-x-auto">
            <table className="w-full min-w-[40rem] text-sm">
              <thead className="text-left text-[11px] uppercase tracking-wide text-neutral-500">
                <tr>
                  <th className="py-1 pr-1 font-medium">Article</th>
                  <th className="py-1 pr-1 font-medium">Qté</th>
                  <th className="py-1 pr-1 font-medium">Éco</th>
                  <th className="py-1 pr-1 font-medium">Standard</th>
                  <th className="py-1 pr-1 font-medium">Luxe</th>
                </tr>
              </thead>
              <tbody>
                {lines.map((line, index) => (
                  <tr key={index} className="border-t border-black/5">
                    <td className="py-1 pr-1">
                      <input className={inputClass} value={line.label} onChange={(e) => updateLine(index, { label: e.target.value })} placeholder="Cahier 200 pages" />
                    </td>
                    <td className="py-1 pr-1">
                      <input className={`${inputClass} w-14`} type="number" min={1} value={line.quantity} onChange={(e) => updateLine(index, { quantity: e.target.value })} />
                    </td>
                    {(['eco', 'standard', 'luxe'] as const).map((tier) => {
                      const brandKey = tier === 'eco' ? 'ecoBrand' : tier === 'standard' ? 'standardBrand' : 'luxeBrand'
                      const priceKey = tier === 'eco' ? 'ecoPrice' : tier === 'standard' ? 'standardPrice' : 'luxePrice'
                      return (
                        <td key={tier} className="py-1 pr-1">
                          <input className={inputClass} value={line[brandKey]} onChange={(e) => updateLine(index, { [brandKey]: e.target.value })} placeholder="Marque" />
                          <input className={`${inputClass} mt-1`} type="number" min={0} value={line[priceKey]} onChange={(e) => updateLine(index, { [priceKey]: e.target.value })} placeholder="Prix u. Ar" />
                        </td>
                      )
                    })}
                  </tr>
                ))}
              </tbody>
            </table>
          </div>
          <div className="flex flex-wrap gap-2">
            <button type="button" className={btnGhost} onClick={() => setLines((current) => [...current, emptyKitLine()])}>
              Ajouter un article
            </button>
            <button type="submit" className={btnPrimary} disabled={busy || !yearId || !gradeId}>
              Publier la liste
            </button>
          </div>
        </form>
        {selected ? (
          <div className="mt-3 border-t border-black/5 pt-2">
            <p className="text-xs text-neutral-500">
              {selected.price_source_label}
              {selected.packs[0]?.supplier?.name ? ` · ${selected.packs[0].supplier.name}` : ''}
            </p>
            <KitSupplyTable definition={selected} />
          </div>
        ) : null}
      </Panel>
      {canManageOrders ? (
        <Panel>
          <h2 className="border-b border-black/5 px-3 py-2 text-sm font-semibold">Choix des familles</h2>
          <table className="w-full text-sm">
            <tbody>
              {orders.map((row) => (
                <tr key={row.id} className="border-t border-black/5">
                  <td className="px-3 py-2">
                    <p className="font-medium">{row.student_name}</p>
                    <p className="text-xs text-neutral-500">{row.pay_instruction}</p>
                  </td>
                  <td className="px-3 py-2 text-right text-xs text-neutral-600">{row.fulfillment_label ?? row.status_label ?? row.status}</td>
                  <td className="px-3 py-2 text-right tabular-nums">{row.total_amount > 0 ? formatAr(row.total_amount) : '—'}</td>
                  <td className="px-3 py-2 text-right">
                    {row.status === 'submitted' ? (
                      <button type="button" className={btnGhost} disabled={busy} onClick={() => void confirmOrder(row.id)}>
                        Confirmer
                      </button>
                    ) : (
                      <span className="text-xs text-neutral-500">{row.status_label ?? row.status}</span>
                    )}
                  </td>
                </tr>
              ))}
            </tbody>
          </table>
          {orders.length === 0 ? <p className="px-3 py-4 text-sm text-neutral-500">Aucun choix pour le moment.</p> : null}
        </Panel>
      ) : null}
    </div>
  )
}

function DirectionScreen({
  session,
  tab,
  onTab,
}: {
  session: Session
  tab: DirectionTab
  onTab: (tab: DirectionTab) => void
}) {
  const schoolId = session.schoolId ?? session.schools[0].id
  const [people, setPeople] = useState<PersonRow[]>([])
  const [yearId, setYearId] = useState('')
  const [yearLabel, setYearLabel] = useState('2026-2027')
  const [years, setYears] = useState<YearRow[]>([])
  const [classrooms, setClassrooms] = useState<ClassroomRow[]>([])
  const [grades, setGrades] = useState<GradeRow[]>([])
  const [feeSchedules, setFeeSchedules] = useState<FeeScheduleRow[]>([])
  const [enrollments, setEnrollments] = useState<EnrollmentRow[]>([])
  const [message, setMessage] = useState<string | null>(null)
  const [invitation, setInvitation] = useState<string | null>(null)
  const [busy, setBusy] = useState(false)
  const [query, setQuery] = useState('')
  const [page, setPage] = useState(1)
  const [classFilter, setClassFilter] = useState('')
  const [selectedClassId, setSelectedClassId] = useState('')
  const [enrollOpen, setEnrollOpen] = useState(false)
  const [newClassName, setNewClassName] = useState('6ème B')
  const [newClassGrade, setNewClassGrade] = useState('')
  const [selectedEnrollment, setSelectedEnrollment] = useState('')
  const [invoice, setInvoice] = useState<InvoiceRow | null>(null)
  const [paymentAmount, setPaymentAmount] = useState('50000')
  const [paymentMethod, setPaymentMethod] = useState('cash')
  const [receipt, setReceipt] = useState<string | null>(null)
  const [cockpit, setCockpit] = useState<Cockpit | null>(null)
  const [reliability, setReliability] = useState<ReliabilityOverview | null>(null)
  const [compareNote, setCompareNote] = useState<string | null>(null)
  const [openFamilyId, setOpenFamilyId] = useState<string | null>(null)
  const [families, setFamilies] = useState<FamilyRow[]>([])
  const [selectedFamilyId, setSelectedFamilyId] = useState<string | null>(null)
  const [familyPane, setFamilyPane] = useState<'foyers' | 'personnes'>('foyers')
  const [shareTokenInput, setShareTokenInput] = useState('')
  const [publicIdInput, setPublicIdInput] = useState('')
  const [transfers, setTransfers] = useState<TransferRow[]>([])
  const [staff, setStaff] = useState<PersonMini[]>([])
  const [terms, setTerms] = useState<TermRow[]>([])
  const [classFile, setClassFile] = useState<ClassFile | null>(null)
  const [financePane, setFinancePane] = useState<'baremes' | 'achats'>('baremes')
  const [newClassCapacity, setNewClassCapacity] = useState('')
  const [newClassTeacher, setNewClassTeacher] = useState('')
  const [selectedPacks, setSelectedPacks] = useState<string[]>([])
  const [network, setNetwork] = useState<SchoolNetwork | null>(null)

  const auth = useMemo(() => ({ token: session.token }), [session.token])
  const activeEnrollments = enrollments.filter((row) => row.status === 'active')
  const filteredPeople = people.filter((person) =>
    matchesQuery(`${person.first_name} ${person.last_name} ${person.public_id}`, query),
  )
  const filteredFamilies = families.filter((family) => matchesQuery(familySearchText(family), query))
  const filteredEnrollments = activeEnrollments.filter((row) => {
    const text = `${row.person?.first_name ?? ''} ${row.person?.last_name ?? ''} ${row.person?.public_id ?? ''}`
    return matchesQuery(text, query) && (classFilter === '' || row.classroom_id === classFilter)
  })
  const pagedPeople = pageOf(filteredPeople, page)
  const pagedFamilies = pageOf(filteredFamilies, page)
  const pagedEnrollments = pageOf(filteredEnrollments, page)
  const selectedStudent = activeEnrollments.find((row) => row.id === selectedEnrollment)
  const selectedFamily = families.find((family) => family.id === selectedFamilyId) ?? null
  const classGroups = classroomsByStage(classrooms)
  const gradeGroups = gradesByStage(grades)

  async function loadCore() {
    const yearsPayload = await api<{ data: YearRow[] }>(`/api/v1/schools/${schoolId}/years`, auth)
    const current = yearsPayload.data.find((year) => year.is_current) ?? yearsPayload.data[0]
    setYears(yearsPayload.data)
    setYearId(current?.id ?? '')
    setYearLabel(current?.label ?? '2026-2027')
    const [classList, gradeList, today, staffList, networkPayload] = await Promise.all([
      api<{ data: ClassroomRow[] }>(`/api/v1/schools/${schoolId}/classrooms`, auth),
      api<{ data: GradeRow[] }>(`/api/v1/schools/${schoolId}/grade-levels`, auth),
      api<Cockpit>(`/api/v1/schools/${schoolId}/cockpit`, auth),
      api<{ data: PersonMini[] }>(`/api/v1/schools/${schoolId}/staff`, auth),
      api<{ data: SchoolNetwork | null }>(`/api/v1/schools/${schoolId}/network`, auth).catch(() => ({ data: null })),
    ])
    setClassrooms(classList.data)
    setGrades(gradeList.data)
    setCockpit(today)
    setStaff(staffList.data)
    setNetwork(networkPayload.data)
    setNewClassGrade((prev) => prev || gradeList.data[0]?.id || '')
  }

  async function loadFeeSchedules() {
    const list = await api<{ data: FeeScheduleRow[] }>(`/api/v1/schools/${schoolId}/fee-schedules`, auth)
    setFeeSchedules(list.data)
  }

  async function loadPeople() {
    const list = await api<{ data: PersonRow[] }>(`/api/v1/schools/${schoolId}/people`, auth)
    setPeople(list.data)
  }

  async function loadFamilies() {
    const list = await api<{ data: FamilyRow[] }>(`/api/v1/schools/${schoolId}/families`, auth)
    setFamilies(list.data)
  }

  async function loadTransfers() {
    const list = await api<{ data: TransferRow[] }>(`/api/v1/schools/${schoolId}/transfers`, auth)
    setTransfers(list.data)
  }

  async function loadReliability() {
    const payload = await api<{ data: ReliabilityOverview }>(`/api/v1/schools/${schoolId}/reliability/overview`, auth)
    setReliability(payload.data)
    setCompareNote(null)
  }

  async function loadEnrollments() {
    const enrollmentList = await api<{ data: EnrollmentRow[] }>(`/api/v1/schools/${schoolId}/enrollments`, auth)
    setEnrollments(enrollmentList.data)
    const active = enrollmentList.data.find((row) => row.status === 'active')
    setSelectedEnrollment((prev) => prev || active?.id || '')
  }

  async function loadTerms() {
    if (!yearId) return
    const payload = await api<{ data: { terms: TermRow[] } }>(`/api/v1/schools/${schoolId}/years/${yearId}`, auth)
    setTerms(payload.data.terms ?? [])
  }

  async function loadClassFile(id: string) {
    const payload = await api<{ data: ClassFile }>(`/api/v1/schools/${schoolId}/classrooms/${id}`, auth)
    setClassFile(payload.data)
  }

  useEffect(() => {
    loadCore().catch((error: Error) => setMessage(error.message))
  }, [schoolId, session.token])

  useEffect(() => {
    if (tab === 'famille') {
      Promise.all([loadPeople(), loadFamilies(), loadTransfers()]).catch((error: Error) => setMessage(error.message))
    }
    if (tab === 'classe') {
      Promise.all([loadEnrollments(), loadTerms()]).catch((error: Error) => setMessage(error.message))
    }
    if (tab === 'caisse') {
      loadEnrollments().catch((error: Error) => setMessage(error.message))
    }
    if (tab === 'finance') {
      loadFeeSchedules().catch((error: Error) => setMessage(error.message))
    }
    if (tab === 'indices') {
      loadReliability().catch((error: Error) => setMessage(error.message))
    }
    if (tab === 'kits') {
      loadEnrollments().catch((error: Error) => setMessage(error.message))
    }
    setQuery('')
    setPage(1)
  }, [tab, schoolId, session.token])

  useEffect(() => {
    setPage(1)
  }, [query, classFilter])

  useEffect(() => {
    if (tab !== 'classe') return
    if (selectedClassId === '' && classrooms[0]) {
      setSelectedClassId(classrooms[0].id)
    }
  }, [tab, classrooms, selectedClassId])

  useEffect(() => {
    if (tab !== 'classe' || !selectedClassId) {
      setClassFile(null)
      return
    }
    loadClassFile(selectedClassId).catch((error: Error) => setMessage(error.message))
  }, [tab, selectedClassId, schoolId, session.token])

  useEffect(() => {
    if (tab === 'classe' && yearId) {
      loadTerms().catch((error: Error) => setMessage(error.message))
    }
  }, [tab, yearId, schoolId, session.token])

  async function redeemShareToken(event: FormEvent) {
    event.preventDefault()
    setBusy(true)
    setMessage(null)
    try {
      await api(`/api/v1/schools/${schoolId}/share-tokens/redeem`, {
        ...auth,
        method: 'POST',
        body: JSON.stringify({ token: shareTokenInput }),
      })
      setShareTokenInput('')
      setMessage('Lien parent racheté. Les identités sont maintenant rattachées à l’école.')
      await Promise.all([loadPeople(), loadFamilies()])
    } catch (error) {
      setMessage(error instanceof Error ? error.message : 'Lien impossible à racheter.')
    } finally {
      setBusy(false)
    }
  }

  async function requestPublicId(event: FormEvent) {
    event.preventDefault()
    setBusy(true)
    setMessage(null)
    try {
      const payload = await api<{ message: string }>(`/api/v1/schools/${schoolId}/person-link-requests`, {
        ...auth,
        method: 'POST',
        body: JSON.stringify({ public_id: publicIdInput }),
      })
      setPublicIdInput('')
      setMessage(payload.message)
    } catch (error) {
      setMessage(error instanceof Error ? error.message : 'Demande impossible.')
    } finally {
      setBusy(false)
    }
  }

  async function resolveSchoolTransfer(transferId: string, action: 'approve' | 'refuse') {
    setBusy(true)
    setMessage(null)
    try {
      await api(`/api/v1/schools/${schoolId}/transfers/${transferId}/${action}`, {
        ...auth,
        method: 'POST',
        body: JSON.stringify({}),
      })
      setMessage(action === 'approve' ? 'Transfert validé par l’école.' : 'Transfert refusé.')
      await loadTransfers()
    } catch (error) {
      setMessage(error instanceof Error ? error.message : 'Transfert impossible à traiter.')
    } finally {
      setBusy(false)
    }
  }

  async function createClassroom(event: FormEvent) {
    event.preventDefault()
    setBusy(true)
    setMessage(null)
    try {
      const payload = await api<{ data: ClassroomRow }>(`/api/v1/schools/${schoolId}/classrooms`, {
        ...auth,
        method: 'POST',
        body: JSON.stringify({
          school_year_id: yearId,
          grade_level_id: newClassGrade,
          name: newClassName,
          ...(newClassCapacity.trim() !== '' ? { capacity: Number(newClassCapacity) } : {}),
          ...(newClassTeacher ? { main_teacher_person_id: newClassTeacher } : {}),
        }),
      })
      setMessage(`${unitLabel(grades.find((grade) => grade.id === newClassGrade)?.stage)} ${newClassName} créé${grades.find((grade) => grade.id === newClassGrade)?.stage === 'preschool' ? '' : 'e'}.`)
      setSelectedClassId(payload.data.id)
      setNewClassTeacher('')
      setNewClassCapacity('')
      await loadCore()
      await loadEnrollments()
    } catch (error) {
      setMessage(error instanceof Error ? error.message : 'Classe impossible à créer.')
    } finally {
      setBusy(false)
    }
  }

  function togglePack(id: string) {
    setSelectedPacks((prev) => {
      const on = prev.includes(id)
      if (on) return prev.filter((pack) => pack !== id)
      let next = [...prev, id]
      if (id === 'primary') next = next.filter((pack) => pack !== 'primary_malagasy')
      if (id === 'primary_malagasy') next = next.filter((pack) => pack !== 'primary')
      return next
    })
  }

  async function applyGradePacks() {
    if (selectedPacks.length === 0) return
    setBusy(true)
    setMessage(null)
    try {
      const payload = await api<{ data: { created: string[]; skipped: string[] } }>(
        `/api/v1/schools/${schoolId}/grade-levels/packs`,
        {
          ...auth,
          method: 'POST',
          body: JSON.stringify({ packs: selectedPacks }),
        },
      )
      const created = payload.data.created.length
      const skipped = payload.data.skipped.length
      setSelectedPacks([])
      setMessage(
        created === 0
          ? 'Ces niveaux existent déjà.'
          : `${created} niveau${created > 1 ? 'x' : ''} ajouté${created > 1 ? 's' : ''}${skipped ? ` · ${skipped} déjà présent${skipped > 1 ? 's' : ''}` : ''}.`,
      )
      await loadCore()
    } catch (error) {
      setMessage(error instanceof Error ? error.message : 'Packs impossibles à appliquer.')
    } finally {
      setBusy(false)
    }
  }

  async function assignClassroom(enrollmentId: string, classroomId: string) {
    setBusy(true)
    setMessage(null)
    try {
      await api(`/api/v1/schools/${schoolId}/enrollments/${enrollmentId}/assign-classroom`, {
        ...auth,
        method: 'POST',
        body: JSON.stringify({ classroom_id: classroomId }),
      })
      await loadEnrollments()
      if (selectedClassId) {
        await loadClassFile(selectedClassId)
      }
    } catch (error) {
      setMessage(error instanceof Error ? error.message : 'Affectation impossible.')
    } finally {
      setBusy(false)
    }
  }

  async function loadInvoice(enrollmentId: string) {
    try {
      const payload = await api<{ data: InvoiceRow }>(`/api/v1/schools/${schoolId}/enrollments/${enrollmentId}/invoice`, auth)
      setInvoice(payload.data)
    } catch {
      setInvoice(null)
    }
  }

  useEffect(() => {
    if (!selectedEnrollment || tab !== 'caisse') return
    loadInvoice(selectedEnrollment).catch(() => setInvoice(null))
  }, [selectedEnrollment, tab, schoolId, session.token])

  async function generateInvoice() {
    setBusy(true)
    setMessage(null)
    setReceipt(null)
    try {
      const payload = await api<{ data: InvoiceRow }>(`/api/v1/schools/${schoolId}/enrollments/${selectedEnrollment}/invoices`, {
        ...auth,
        method: 'POST',
        body: JSON.stringify({}),
      })
      setInvoice(payload.data)
      setMessage(`Facture ${payload.data.number} émise.`)
      await Promise.all([loadEnrollments(), loadCore()])
    } catch (error) {
      setMessage(error instanceof Error ? error.message : 'Facture impossible à générer.')
    } finally {
      setBusy(false)
    }
  }

  async function recordPayment(event: FormEvent) {
    event.preventDefault()
    if (!invoice) return
    setBusy(true)
    setMessage(null)
    try {
      const payload = await api<{ data: { receipt: { number: string } }; invoice: InvoiceRow }>(
        `/api/v1/schools/${schoolId}/payments`,
        {
          ...auth,
          method: 'POST',
          body: JSON.stringify({
            invoice_id: invoice.id,
            amount: Number(paymentAmount),
            method: paymentMethod,
            received_on: new Date().toISOString().slice(0, 10),
            idempotency_key: crypto.randomUUID(),
          }),
        },
      )
      setReceipt(payload.data.receipt.number)
      setInvoice(payload.invoice)
      setMessage(`Paiement enregistré. Reçu ${payload.data.receipt.number}.`)
      await Promise.all([loadEnrollments(), loadCore()])
    } catch (error) {
      setMessage(error instanceof Error ? error.message : 'Paiement impossible à enregistrer.')
    } finally {
      setBusy(false)
    }
  }

  async function relance(taskId: string) {
    setBusy(true)
    setMessage(null)
    try {
      await api(`/api/v1/schools/${schoolId}/collection/tasks/${taskId}/relance`, {
        ...auth,
        method: 'POST',
        body: JSON.stringify({}),
      })
      setMessage('Relance enregistrée. Avis imprimé et message envoyé à la famille.')
      await loadCore()
    } catch (error) {
      setMessage(error instanceof Error ? error.message : 'Relance impossible.')
    } finally {
      setBusy(false)
    }
  }

  async function acknowledgeAlert(alertId: string) {
    setBusy(true)
    setMessage(null)
    try {
      await api(`/api/v1/schools/${schoolId}/alerts/${alertId}/acknowledge`, {
        ...auth,
        method: 'POST',
        body: JSON.stringify({}),
      })
      setMessage('Signalement accusé. Un suivi humain reste requis.')
      await loadCore()
    } catch (error) {
      setMessage(error instanceof Error ? error.message : 'Accusé impossible.')
    } finally {
      setBusy(false)
    }
  }

  async function downloadCsv() {
    const response = await fetch(`/api/v1/schools/${schoolId}/payments/export`, {
      headers: { Authorization: `Bearer ${session.token}`, Accept: 'text/csv' },
    })
    const blob = await response.blob()
    const url = URL.createObjectURL(blob)
    const link = document.createElement('a')
    link.href = url
    link.download = 'paiements-fanabe.csv'
    link.click()
    URL.revokeObjectURL(url)
  }

  async function verifySchoolRecalc() {
    setBusy(true)
    setCompareNote(null)
    try {
      const payload = await api<{ data: { digest_match: boolean; version_match: boolean } }>(
        `/api/v1/schools/${schoolId}/reliability/school/compare`,
        auth,
      )
      if (payload.data.digest_match && payload.data.version_match) {
        setCompareNote('Recalcul identique — mêmes faits, même version.')
      } else if (!payload.data.version_match) {
        setCompareNote('La version du calculateur a changé.')
      } else {
        setCompareNote('Le digest a changé : les faits ont bougé depuis le dernier enregistrement.')
      }
    } catch (error) {
      setMessage(error instanceof Error ? error.message : 'Comparaison impossible.')
    } finally {
      setBusy(false)
    }
  }

  const filteredReliabilityFamilies = (reliability?.families ?? []).filter((row) => {
    const names = row.students.map((student) => `${student.first_name} ${student.last_name}`).join(' ')
    return matchesQuery(names, query)
  })
  const pagedReliabilityFamilies = pageOf(filteredReliabilityFamilies, page)

  return (
    <>
    <main className="px-3 py-3 sm:px-4">
      {message ? <Banner message={message} onClear={() => setMessage(null)} /> : null}

      {tab === 'accueil' ? (
        <div className="space-y-3">
          <div className="grid grid-cols-2 overflow-hidden rounded-lg border border-black/8 bg-black/[0.04] lg:grid-cols-4">
            <Kpi label="Présents" value={String(cockpit?.attendance.present ?? 0)} hint={`${cockpit?.attendance.absent ?? 0} abs.`} />
            <Kpi label="Encaissé" value={formatAr(cockpit?.collected_today ?? 0)} hint="aujourd’hui" />
            <Kpi label="Reste dû" value={formatAr(cockpit?.outstanding_amount ?? 0)} hint={yearLabel} />
            <Kpi label="À relancer" value={String(cockpit?.actions.length ?? 0)} hint="faits, pas un score" />
          </div>
          <Panel>
            <div className="flex items-center justify-between gap-3 border-b border-black/5 px-3 py-2">
              <h2 className="text-sm font-semibold">Actions du jour</h2>
              {cockpit?.forecast ? (
                <p className="text-xs text-neutral-500">
                  Semaine {formatAr(cockpit.forecast.expected_amount)}
                  <span className="hidden sm:inline">
                    {' '}
                    ({formatAr(cockpit.forecast.confidence_low_amount)}–{formatAr(cockpit.forecast.confidence_high_amount)})
                  </span>
                </p>
              ) : null}
            </div>
            <table className="w-full text-sm">
              <tbody>
                {(cockpit?.actions ?? []).map((row) => (
                  <tr key={row.id} className="border-t border-black/5 first:border-t-0">
                    <td className="px-3 py-2">
                      <p className="font-medium">
                        {row.student ? `${row.student.first_name} ${row.student.last_name}` : row.title}
                      </p>
                      <p className="text-xs text-neutral-600">{row.reason_summary}</p>
                    </td>
                    <td className="w-24 px-3 py-2 text-right">
                      <button type="button" disabled={busy || row.status === 'resolved'} className={btnPrimary} onClick={() => relance(row.id)}>
                        Relancer
                      </button>
                    </td>
                  </tr>
                ))}
                {(cockpit?.actions.length ?? 0) === 0 ? (
                  <tr>
                    <td className="px-3 py-4 text-sm text-neutral-600" colSpan={2}>
                      Rien à relancer pour le moment.
                    </td>
                  </tr>
                ) : null}
              </tbody>
            </table>
          </Panel>
          <Panel>
            <div className="flex items-center justify-between gap-3 border-b border-black/5 px-3 py-2">
              <h2 className="text-sm font-semibold">Attention</h2>
              <p className="text-xs text-neutral-500">Signalements, pas un jugement</p>
            </div>
            <table className="w-full text-sm">
              <tbody>
                {(cockpit?.attention ?? []).map((row) => (
                  <tr key={row.id} className="border-t border-black/5 first:border-t-0">
                    <td className="px-3 py-2">
                      <p className="font-medium">
                        {row.student ? `${row.student.first_name} ${row.student.last_name}` : 'Élève'}
                      </p>
                      <p className="text-xs text-neutral-600">{row.reason_summary}</p>
                    </td>
                    <td className="w-28 px-3 py-2 text-right">
                      <button type="button" disabled={busy} className={btnGhost} onClick={() => acknowledgeAlert(row.id)}>
                        Accuser
                      </button>
                    </td>
                  </tr>
                ))}
                {(cockpit?.attention?.length ?? 0) === 0 ? (
                  <tr>
                    <td className="px-3 py-4 text-sm text-neutral-600" colSpan={2}>
                      Aucun signalement ouvert.
                    </td>
                  </tr>
                ) : null}
              </tbody>
            </table>
          </Panel>
          <div className="flex flex-wrap gap-2 text-xs">
            <button
              type="button"
              className={btnGhost}
              onClick={() => {
                onTab('famille')
                setEnrollOpen(true)
                loadFamilies().catch((error: Error) => setMessage(error.message))
              }}
            >
              Inscrire un élève
            </button>
            <button type="button" className={btnGhost} onClick={() => onTab('classe')}>
              Classes
            </button>
            <button type="button" className={btnGhost} onClick={() => onTab('caisse')}>
              Enregistrer un paiement
            </button>
          </div>
        </div>
      ) : null}

      {tab === 'famille' ? (
        <div className="grid gap-3 lg:grid-cols-[18rem_1fr]">
          <div className="space-y-3">
            <Panel className="p-3">
              <h2 className="text-sm font-semibold">Inscription</h2>
              <p className="mt-1 text-xs text-neutral-500">Élève d’abord, puis le foyer.</p>
              <button type="button" className={`${btnBlock} mt-3`} disabled={!yearId} onClick={() => {
                setEnrollOpen(true)
                loadFamilies().catch((error: Error) => setMessage(error.message))
              }}>
                Inscrire un élève
              </button>
              {invitation ? (
                <div className="mt-3 flex items-center justify-between gap-2 rounded-md bg-fanabe-mist px-2 py-2 text-xs">
                  <strong className="font-mono">{invitation}</strong>
                  <button type="button" className={btnGhost} onClick={() => copyText(invitation)}>
                    Copier
                  </button>
                </div>
              ) : null}
            </Panel>
            <Panel className="p-3">
              <h2 className="text-sm font-semibold">Lien existant</h2>
              <form onSubmit={redeemShareToken} className="mt-3 space-y-2">
                <input className={inputClass} value={shareTokenInput} onChange={(e) => setShareTokenInput(e.target.value)} placeholder="Jeton parent" />
                <button type="submit" disabled={busy || shareTokenInput.trim() === ''} className={btnBlock}>
                  Racheter le lien
                </button>
              </form>
              <form onSubmit={requestPublicId} className="mt-3 space-y-2">
                <input className={inputClass} value={publicIdInput} onChange={(e) => setPublicIdInput(e.target.value)} placeholder="Identifiant 7-XXXXXXXX-C" />
                <button type="submit" disabled={busy || publicIdInput.trim() === ''} className={btnBlock}>
                  Demander le rattachement
                </button>
              </form>
            </Panel>
            {transfers.length > 0 ? (
              <Panel className="p-3">
                <h2 className="text-sm font-semibold">Transferts</h2>
                <ul className="mt-2 space-y-2 text-xs">
                  {transfers.map((row) => (
                    <li key={row.id} className="rounded-md border border-black/8 p-2">
                      <p className="font-medium">
                        {row.person ? `${row.person.first_name} ${row.person.last_name}` : 'Élève'}
                      </p>
                      <p className="text-neutral-600">
                        {row.origin_school} → {row.destination_school} · {transferLabel(row.status)}
                      </p>
                      {row.status === 'pending_origin_school' || row.status === 'pending_parent' ? (
                        <div className="mt-2 flex gap-1">
                          <button type="button" className={btnPrimary} disabled={busy} onClick={() => void resolveSchoolTransfer(row.id, 'approve')}>
                            Valider
                          </button>
                          <button type="button" className={btnGhost} disabled={busy} onClick={() => void resolveSchoolTransfer(row.id, 'refuse')}>
                            Refuser
                          </button>
                        </div>
                      ) : null}
                    </li>
                  ))}
                </ul>
              </Panel>
            ) : null}
          </div>
          {selectedFamily ? (
            <FamilyEditor
              family={selectedFamily}
              schoolId={schoolId}
              yearId={yearId}
              classrooms={classrooms}
              auth={auth}
              busy={busy}
              onBusy={setBusy}
              onMessage={setMessage}
              onInvitation={setInvitation}
              onReload={async () => {
                await Promise.all([loadFamilies(), loadPeople(), loadEnrollments()])
              }}
              onClose={() => setSelectedFamilyId(null)}
            />
          ) : (
            <Panel className="min-w-0">
              <div className="flex flex-wrap items-center gap-2 border-b border-black/5 px-3 py-2">
                <div className="flex rounded-md bg-black/5 p-0.5">
                  <button type="button" className={modeTab(familyPane === 'foyers')} onClick={() => setFamilyPane('foyers')}>
                    Foyers
                  </button>
                  <button type="button" className={modeTab(familyPane === 'personnes')} onClick={() => setFamilyPane('personnes')}>
                    Personnes
                  </button>
                </div>
                <input className={`${inputClass} max-w-xs`} value={query} onChange={(e) => setQuery(e.target.value)} placeholder="Nom ou identifiant" />
                <Pager
                  page={page}
                  total={familyPane === 'foyers' ? filteredFamilies.length : filteredPeople.length}
                  onPage={setPage}
                />
              </div>
              <div className="overflow-auto">
                {familyPane === 'foyers' ? (
                  <table className="w-full text-sm">
                    <thead className="bg-black/[0.03] text-left text-[11px] uppercase tracking-wide text-neutral-500">
                      <tr>
                        <th className="px-3 py-1.5 font-medium">Foyer</th>
                        <th className="px-3 py-1.5 font-medium">Enfants</th>
                        <th className="px-3 py-1.5 font-medium">Adultes</th>
                      </tr>
                    </thead>
                    <tbody>
                      {pagedFamilies.map((family) => {
                        const children = family.members.filter((member) => member.role_in_family === 'child')
                        const adults = family.members.filter((member) => member.role_in_family === 'adult')
                        return (
                          <tr key={family.id} className="cursor-pointer border-t border-black/5 hover:bg-black/[0.03]" onClick={() => setSelectedFamilyId(family.id)}>
                            <td className="px-3 py-1.5 font-medium">{family.label}</td>
                            <td className="px-3 py-1.5 text-neutral-600">
                              {children.map((member) => `${member.first_name} ${member.last_name}`).join(', ') || '—'}
                            </td>
                            <td className="px-3 py-1.5 text-neutral-600">
                              {adults.map((member) => `${member.first_name} ${member.last_name}`).join(', ') || '—'}
                            </td>
                          </tr>
                        )
                      })}
                    </tbody>
                  </table>
                ) : (
                  <table className="w-full text-sm">
                    <thead className="bg-black/[0.03] text-left text-[11px] uppercase tracking-wide text-neutral-500">
                      <tr>
                        <th className="px-3 py-1.5 font-medium">Nom</th>
                        <th className="px-3 py-1.5 font-medium">Rôle</th>
                        <th className="px-3 py-1.5 font-medium">Identifiant</th>
                      </tr>
                    </thead>
                    <tbody>
                      {pagedPeople.map((person) => (
                        <tr key={`${person.id}-${person.kind ?? ''}`} className="border-t border-black/5">
                          <td className="px-3 py-1.5 font-medium">
                            {person.first_name} {person.last_name}
                          </td>
                          <td className="px-3 py-1.5 text-neutral-600">{kindLabel(person.kind)}</td>
                          <td className="px-3 py-1.5 font-mono text-xs text-neutral-600">{person.public_id}</td>
                        </tr>
                      ))}
                    </tbody>
                  </table>
                )}
              </div>
            </Panel>
          )}
        </div>
      ) : null}

      {tab === 'classe' ? (
        <div className="grid gap-3 lg:grid-cols-[16rem_1fr]">
          <Panel className="p-3">
            <h2 className="text-sm font-semibold">Classes · {yearLabel}</h2>
            {network ? (
              <p className="mt-1 text-xs text-neutral-600">
                Campus · {network.name}
                {network.campuses.filter((campus) => campus.id !== schoolId).length > 0
                  ? ` · ${network.campuses
                      .filter((campus) => campus.id !== schoolId)
                      .map((campus) => campus.name)
                      .join(', ')}`
                  : ''}
              </p>
            ) : null}
            <form onSubmit={createClassroom} className="mt-3 space-y-2">
              <select className={inputClass} value={newClassGrade} onChange={(e) => setNewClassGrade(e.target.value)}>
                {gradeGroups.length <= 1
                  ? grades.map((grade) => (
                      <option key={grade.id} value={grade.id}>
                        {grade.name}
                      </option>
                    ))
                  : gradeGroups.map((group) => (
                      <optgroup key={group.stage || 'other'} label={group.label}>
                        {group.rows.map((grade) => (
                          <option key={grade.id} value={grade.id}>
                            {grade.name}
                          </option>
                        ))}
                      </optgroup>
                    ))}
              </select>
              <input className={inputClass} value={newClassName} onChange={(e) => setNewClassName(e.target.value)} required />
              <input
                className={inputClass}
                type="number"
                min={1}
                value={newClassCapacity}
                onChange={(e) => setNewClassCapacity(e.target.value)}
                placeholder="Capacité"
              />
              <select className={inputClass} value={newClassTeacher} onChange={(e) => setNewClassTeacher(e.target.value)}>
                <option value="">Titulaire (optionnel)</option>
                {staff.map((person) => (
                  <option key={person.id} value={person.id}>
                    {personLabel(person)}
                  </option>
                ))}
              </select>
              <button type="submit" disabled={busy || !yearId || !newClassGrade} className={btnBlock}>
                Créer
              </button>
            </form>
            <ul className="mt-3 divide-y divide-black/5 text-sm">
              {classGroups.map((group, index) => (
                <li key={group.stage || 'other'}>
                  {classGroups.length > 1 ? (
                    <p className={`text-[11px] font-semibold uppercase tracking-wide text-neutral-500 ${index === 0 ? '' : 'pt-2'}`}>
                      {group.label}
                    </p>
                  ) : null}
                  <ul>
                    {group.rows.map((classroom) => {
                      const count = activeEnrollments.filter((row) => row.classroom_id === classroom.id).length
                      const kind = classroom.grade_level?.unit_label ?? unitLabel(classroom.grade_level?.stage)
                      return (
                        <li key={classroom.id}>
                          <button
                            type="button"
                            className={`flex w-full items-center justify-between py-1.5 text-left ${selectedClassId === classroom.id ? 'font-semibold text-fanabe-leaf' : ''}`}
                            onClick={() => setSelectedClassId(classroom.id)}
                          >
                            <span>
                              {kind === 'Groupe' ? <span className="mr-1 text-[11px] font-normal text-neutral-500">Groupe</span> : null}
                              {classroom.name}
                            </span>
                            <span className="text-xs text-neutral-500">{count}</span>
                          </button>
                        </li>
                      )
                    })}
                  </ul>
                </li>
              ))}
            </ul>
            <div className="mt-3 border-t border-black/5 pt-3">
              <p className="text-[11px] font-semibold uppercase tracking-wide text-neutral-500">Niveaux</p>
              <p className="mt-0.5 text-[11px] text-neutral-500">Cases à cocher, pas un type d’école. T1–T5 est une variante.</p>
              <ul className="mt-2 space-y-1">
                {GRADE_PACKS.map((pack) => (
                  <li key={pack.id}>
                    <label className="flex cursor-pointer items-start gap-2 text-xs">
                      <input
                        type="checkbox"
                        className="mt-0.5"
                        checked={selectedPacks.includes(pack.id)}
                        onChange={() => togglePack(pack.id)}
                      />
                      <span>
                        <span className="font-medium">{pack.label}</span>
                        <span className="text-neutral-500"> · {pack.hint}</span>
                      </span>
                    </label>
                  </li>
                ))}
              </ul>
              <button
                type="button"
                className={`${btnGhost} mt-2`}
                disabled={busy || selectedPacks.length === 0}
                onClick={() => void applyGradePacks()}
              >
                Ajouter les niveaux
              </button>
            </div>
          </Panel>
          <Panel className="min-w-0">
            {classFile ? (
              <ClassFilePanel
                schoolId={schoolId}
                auth={auth}
                file={classFile}
                staff={staff}
                terms={terms}
                classrooms={classrooms}
                busy={busy}
                onBusy={setBusy}
                onMessage={setMessage}
                onReload={async () => {
                  if (selectedClassId) await loadClassFile(selectedClassId)
                  await Promise.all([loadCore(), loadEnrollments()])
                }}
                onAssignClassroom={assignClassroom}
              />
            ) : (
              <p className="px-3 py-8 text-center text-sm text-neutral-500">
                Ouvrez une classe ou un groupe pour son dossier : titulaire, effectif, emploi du temps et activités.
              </p>
            )}
          </Panel>
        </div>
      ) : null}

      {tab === 'finance' ? (
        <div className="space-y-3">
          <div className="flex w-fit rounded-md bg-black/5 p-0.5">
            <button type="button" className={modeTab(financePane === 'baremes')} onClick={() => setFinancePane('baremes')}>
              Barèmes
            </button>
            <button type="button" className={modeTab(financePane === 'achats')} onClick={() => setFinancePane('achats')}>
              Achats
            </button>
          </div>
          {financePane === 'baremes' ? (
            <FeeSettingsPanel
              schoolId={schoolId}
              auth={auth}
              years={years}
              grades={grades}
              currentYearId={yearId}
              schedules={feeSchedules}
              busy={busy}
              onBusy={setBusy}
              onMessage={setMessage}
              onRefresh={async () => {
                await Promise.all([loadFeeSchedules(), loadCore()])
              }}
            />
          ) : (
            <ExpensesPanel
              schoolId={schoolId}
              auth={auth}
              years={years}
              currentYearId={yearId}
              busy={busy}
              onBusy={setBusy}
              onMessage={setMessage}
            />
          )}
        </div>
      ) : null}

      {tab === 'caisse' ? (
        <div className="grid gap-3 lg:grid-cols-[minmax(0,1fr)_20rem]">
          <Panel className="min-w-0">
            <div className="flex flex-wrap items-center gap-2 border-b border-black/5 px-3 py-2">
              <input className={`${inputClass} max-w-[12rem]`} value={query} onChange={(e) => setQuery(e.target.value)} placeholder="Nom ou classe" />
              <select className={`${inputClass} w-auto`} value={classFilter} onChange={(e) => setClassFilter(e.target.value)}>
                <option value="">Toutes les classes</option>
                {classrooms.map((classroom) => (
                  <option key={classroom.id} value={classroom.id}>
                    {classroom.name}
                  </option>
                ))}
              </select>
              <button type="button" className={`${btnGhost} ml-auto`} onClick={() => downloadCsv().catch((error: Error) => setMessage(error.message))}>
                CSV
              </button>
              <Pager page={page} total={filteredEnrollments.length} onPage={setPage} />
            </div>
            <div className="overflow-auto">
              <table className="w-full text-sm">
                <thead className="bg-black/[0.03] text-left text-[11px] uppercase tracking-wide text-neutral-500">
                  <tr>
                    <th className="px-3 py-1.5 font-medium">Élève</th>
                    <th className="px-3 py-1.5 font-medium">Classe</th>
                    <th className="px-3 py-1.5 text-right font-medium">Reste</th>
                  </tr>
                </thead>
                <tbody>
                  {pagedEnrollments.map((row) => {
                    const selected = row.id === selectedEnrollment
                    return (
                      <tr key={row.id} className={`border-t border-black/5 ${selected ? 'bg-fanabe-mist' : 'hover:bg-black/[0.03]'}`}>
                        <td className="px-3 py-1">
                          <button type="button" className="w-full text-left font-medium" onClick={() => setSelectedEnrollment(row.id)}>
                            {row.person?.first_name} {row.person?.last_name}
                          </button>
                        </td>
                        <td className="px-3 py-1 text-neutral-600">{row.classroom?.name ?? '—'}</td>
                        <td className="px-3 py-1 text-right tabular-nums">
                          {row.invoice ? formatAr(row.invoice.remaining_amount) : '—'}
                        </td>
                      </tr>
                    )
                  })}
                </tbody>
              </table>
            </div>
          </Panel>
          <Panel className="p-3">
            <h2 className="text-sm font-semibold">
              {selectedStudent ? `${selectedStudent.person?.first_name} ${selectedStudent.person?.last_name}` : 'Caisse'}
            </h2>
            <p className="mt-1 text-xs text-neutral-500">Enregistrement hors ligne — FANABE n’encaisse pas.</p>
            <button type="button" disabled={busy || !selectedEnrollment} className={`${btnGhost} mt-3`} onClick={generateInvoice}>
              Générer la facture
            </button>
            {invoice ? (
              <div className="mt-3 space-y-2 text-sm">
                <div className="flex items-center justify-between">
                  <span className="font-medium">{invoice.number}</span>
                  <span className="text-xs text-neutral-500">{invoiceLabel(invoice.status)}</span>
                </div>
                <div className="h-1.5 overflow-hidden rounded-full bg-black/10">
                  <div
                    className="h-full bg-fanabe-leaf"
                    style={{ width: `${invoice.net_amount === 0 ? 0 : Math.min(100, (invoice.paid_amount / invoice.net_amount) * 100)}%` }}
                  />
                </div>
                <p className="text-xs">
                  {formatAr(invoice.paid_amount)} / {formatAr(invoice.net_amount)} · reste <strong>{formatAr(invoice.remaining_amount)}</strong>
                </p>
                <ul className="space-y-1 text-xs text-neutral-600">
                  {invoice.installments.map((row) => (
                    <li key={row.id} className="flex justify-between gap-2">
                      <span>{formatDate(row.due_on)}</span>
                      <span>{formatAr(row.remaining_amount)}</span>
                    </li>
                  ))}
                </ul>
              </div>
            ) : (
              <p className="mt-3 text-xs text-neutral-500">Pas encore de facture.</p>
            )}
            <form onSubmit={recordPayment} className="mt-3 space-y-2">
              <Field label="Montant (Ar)">
                <input className={inputClass} type="number" min={1} step={1} value={paymentAmount} onChange={(e) => setPaymentAmount(e.target.value)} required />
              </Field>
              <select className={inputClass} value={paymentMethod} onChange={(e) => setPaymentMethod(e.target.value)}>
                <option value="cash">Espèces</option>
                <option value="mobile_money">Mobile money</option>
                <option value="bank_transfer">Virement</option>
              </select>
              <button type="submit" disabled={busy || !invoice} className={btnBlock}>
                {busy ? 'Enregistrement…' : 'Enregistrer'}
              </button>
            </form>
            {receipt ? (
              <div className="mt-2 flex items-center justify-between gap-2 text-xs">
                <span>
                  Reçu <strong>{receipt}</strong>
                </span>
                <button type="button" className={btnGhost} onClick={() => copyText(receipt)}>
                  Copier
                </button>
              </div>
            ) : null}
          </Panel>
        </div>
      ) : null}

      {tab === 'kits' ? (
        <KitsPanel
          schoolId={schoolId}
          auth={auth}
          yearId={yearId}
          years={years}
          grades={grades}
          busy={busy}
          onBusy={setBusy}
          onMessage={setMessage}
        />
      ) : null}

      {tab === 'indices' ? (
        <div className="space-y-3">
          <div className="grid grid-cols-2 overflow-hidden rounded-lg border border-black/8 bg-black/[0.04] lg:grid-cols-4">
            <Kpi
              label="École"
              value={scoreLabel(reliability?.school)}
              hint={reliability?.school.calculator_version ?? 'school-reliability.v1'}
            />
            <Kpi
              label="Faits"
              value={String(reliability?.school.event_count ?? 0)}
              hint="factures, paiements, relances"
            />
            <Kpi
              label="Familles"
              value={String(reliability?.families.length ?? 0)}
              hint="foyers inscrits"
            />
            <Kpi label="Visibilité" value="Direction" hint="jamais un parent, jamais une autre école" />
          </div>
          <Panel className="p-3">
            <div className="flex flex-wrap items-center justify-between gap-2">
              <h2 className="text-sm font-semibold">Fiabilité de l’établissement</h2>
              <button type="button" className={btnGhost} disabled={busy} onClick={verifySchoolRecalc}>
                Vérifier le recalcul
              </button>
            </div>
            <p className="mt-1 text-xs text-neutral-500">
              Indice versionné, décomposable, reproductible. Il n’autorise ni ne bloque rien.
            </p>
            {compareNote ? <p className="mt-2 text-xs text-fanabe-leaf-dark">{compareNote}</p> : null}
            <ul className="mt-3 space-y-1 text-sm">
              {(reliability?.school?.factors ?? []).map((factor) => (
                <li key={factor.event_type} className="flex justify-between gap-3">
                  <span>
                    {factor.human_label}{' '}
                    <span className="text-xs text-neutral-500">({factor.event_count})</span>
                  </span>
                  <span className="tabular-nums">{factor.contribution > 0 ? `+${factor.contribution}` : factor.contribution}</span>
                </li>
              ))}
              {(reliability?.school?.factors.length ?? 0) === 0 ? (
                <li className="text-xs text-neutral-500">Pas encore de faits d’école.</li>
              ) : null}
            </ul>
          </Panel>
          <Panel>
            <div className="flex flex-wrap items-center justify-between gap-2 border-b border-black/5 px-3 py-2">
              <h2 className="text-sm font-semibold">Familles</h2>
              <input
                className={`${inputClass} max-w-56`}
                placeholder="Rechercher"
                value={query}
                onChange={(event) => setQuery(event.target.value)}
              />
            </div>
            <table className="w-full text-sm">
              <thead>
                <tr className="text-left text-[11px] uppercase tracking-wide text-neutral-500">
                  <th className="px-3 py-1.5 font-medium">Élèves</th>
                  <th className="px-3 py-1.5 font-medium">Famille</th>
                  <th className="px-3 py-1.5 font-medium">Relation</th>
                </tr>
              </thead>
              <tbody>
                {pagedReliabilityFamilies.map((row) => {
                  const open = openFamilyId === row.family_id
                  return (
                    <Fragment key={row.family_id}>
                      <tr
                        className="cursor-pointer border-t border-black/5 hover:bg-black/[0.03]"
                        onClick={() => setOpenFamilyId(open ? null : row.family_id)}
                      >
                        <td className="px-3 py-1.5">
                          {row.students.map((student) => `${student.first_name} ${student.last_name}`).join(', ') || '—'}
                        </td>
                        <td className="px-3 py-1.5">{scoreLabel(row.family_reliability)}</td>
                        <td className="px-3 py-1.5">{scoreLabel(row.relationship_health)}</td>
                      </tr>
                      {open ? (
                        <tr className="border-t border-black/5 bg-black/[0.02]">
                          <td colSpan={3} className="px-3 py-2 text-xs text-neutral-600">
                            <p className="font-medium text-neutral-800">Explicabilité</p>
                            <p className="mt-1">
                              Famille {row.family_reliability.calculator_version}
                              {' · '}
                              Relation {row.relationship_health.calculator_version}
                            </p>
                            <ul className="mt-2 space-y-0.5">
                              {row.family_reliability.factors.map((factor) => (
                                <li key={`f-${factor.event_type}`} className="flex justify-between gap-3">
                                  <span>{factor.human_label}</span>
                                  <span className="tabular-nums">
                                    {factor.contribution > 0 ? `+${factor.contribution}` : factor.contribution}
                                  </span>
                                </li>
                              ))}
                              {row.relationship_health.factors.map((factor) => (
                                <li key={`r-${factor.event_type}`} className="flex justify-between gap-3">
                                  <span>{factor.human_label}</span>
                                  <span className="tabular-nums">
                                    {factor.contribution > 0 ? `+${factor.contribution}` : factor.contribution}
                                  </span>
                                </li>
                              ))}
                            </ul>
                            {row.family_reliability.factors.length + row.relationship_health.factors.length === 0 ? (
                              <p className="mt-1">Aucun facteur pour l’instant.</p>
                            ) : null}
                          </td>
                        </tr>
                      ) : null}
                    </Fragment>
                  )
                })}
              </tbody>
            </table>
            <div className="border-t border-black/5 px-3 py-2">
              <Pager page={page} total={filteredReliabilityFamilies.length} onPage={setPage} />
            </div>
          </Panel>
        </div>
      ) : null}
    </main>
    {enrollOpen ? (
      <EnrollmentWizard
        yearId={yearId}
        yearLabel={yearLabel}
        schoolId={schoolId}
        auth={auth}
        families={families}
        classrooms={classrooms}
        busy={busy}
        onBusy={setBusy}
        onClose={() => setEnrollOpen(false)}
        onEnrolled={async ({ familyId, invitation: code }) => {
          setInvitation(code)
          setSelectedFamilyId(familyId)
          setFamilyPane('foyers')
          await Promise.all([loadPeople(), loadFamilies(), loadEnrollments(), loadCore()])
        }}
      />
    ) : null}
    </>
  )
}

function Kpi({ label, value, hint }: { label: string; value: string; hint: string }) {
  return (
    <div className="bg-fanabe-paper px-3 py-2">
      <p className="text-[10px] font-semibold uppercase tracking-wide text-neutral-500">{label}</p>
      <p className="text-lg font-semibold leading-tight">{value}</p>
      <p className="text-[11px] text-neutral-500">{hint}</p>
    </div>
  )
}

function TeacherScreen({ session, tab }: { session: Session; tab: TeacherTab }) {
  const schoolId = session.schoolId ?? session.schools[0].id
  const [classrooms, setClassrooms] = useState<ClassroomRow[]>([])
  const [selectedClassroom, setSelectedClassroom] = useState('')
  const [classFile, setClassFile] = useState<ClassFile | null>(null)
  const [students, setStudents] = useState<RosterStudent[]>([])
  const [query, setQuery] = useState('')
  const [attendanceDate, setAttendanceDate] = useState(() => new Date().toISOString().slice(0, 10))
  const [marks, setMarks] = useState<Record<string, string>>({})
  const [message, setMessage] = useState<string | null>(null)
  const [busy, setBusy] = useState(false)
  const [years, setYears] = useState<YearRow[]>([])
  const [yearId, setYearId] = useState('')
  const auth = useMemo(() => ({ token: session.token }), [session.token])
  const currentClass = classrooms.find((row) => row.id === selectedClassroom)
  const visibleStudents = students.filter((row) =>
    matchesQuery(`${row.person?.first_name ?? ''} ${row.person?.last_name ?? ''} ${row.person?.public_id ?? ''}`, query),
  )

  async function refresh() {
    const [classList, yearsPayload] = await Promise.all([
      api<{ data: ClassroomRow[] }>(`/api/v1/schools/${schoolId}/classrooms`, auth),
      api<{ data: YearRow[] }>(`/api/v1/schools/${schoolId}/years`, auth),
    ])
    setClassrooms(classList.data)
    setSelectedClassroom((prev) => prev || classList.data[0]?.id || '')
    const current = yearsPayload.data.find((year) => year.is_current) ?? yearsPayload.data[0]
    setYears(yearsPayload.data)
    setYearId(current?.id ?? classList.data[0]?.school_year_id ?? '')
  }

  async function loadRoster(classroomId: string) {
    const payload = await api<{ data: { students: RosterStudent[] } }>(
      `/api/v1/schools/${schoolId}/classrooms/${classroomId}/roster`,
      auth,
    )
    setStudents(payload.data.students)
  }

  async function loadClassFile(classroomId: string) {
    const payload = await api<{ data: ClassFile }>(`/api/v1/schools/${schoolId}/classrooms/${classroomId}`, auth)
    setClassFile(payload.data)
  }

  async function loadAttendance(classroomId: string, date: string) {
    const payload = await api<{
      data: Array<{ enrollment_id: string; attendance: { status: string } | null }>
    }>(`/api/v1/schools/${schoolId}/attendance?classroom_id=${classroomId}&date=${date}&session=full_day`, auth)
    const next: Record<string, string> = {}
    for (const row of payload.data) {
      next[row.enrollment_id] = row.attendance?.status ?? 'present'
    }
    setMarks(next)
  }

  useEffect(() => {
    refresh().catch((error: Error) => setMessage(error.message))
  }, [schoolId, session.token])

  useEffect(() => {
    if (!selectedClassroom) return
    loadRoster(selectedClassroom).catch((error: Error) => setMessage(error.message))
  }, [selectedClassroom, schoolId, session.token])

  useEffect(() => {
    if (!selectedClassroom || tab !== 'classe') return
    loadClassFile(selectedClassroom).catch((error: Error) => setMessage(error.message))
  }, [selectedClassroom, tab, schoolId, session.token])

  useEffect(() => {
    if (!selectedClassroom || tab !== 'appel') return
    loadAttendance(selectedClassroom, attendanceDate).catch((error: Error) => setMessage(error.message))
  }, [selectedClassroom, attendanceDate, tab, schoolId, session.token])

  async function saveAttendance(event: FormEvent) {
    event.preventDefault()
    setBusy(true)
    setMessage(null)
    try {
      await api(`/api/v1/schools/${schoolId}/attendance`, {
        ...auth,
        method: 'POST',
        body: JSON.stringify({
          date: attendanceDate,
          session: 'full_day',
          records: students.map((row) => ({
            enrollment_id: row.enrollment_id,
            status: marks[row.enrollment_id] ?? 'present',
            client_reference: crypto.randomUUID(),
          })),
        }),
      })
      setMessage('Présence enregistrée pour la classe.')
    } catch (error) {
      setMessage(error instanceof Error ? error.message : 'Présence impossible à enregistrer.')
    } finally {
      setBusy(false)
    }
  }

  return (
    <main className="px-3 py-3 sm:px-4">
      {message ? <Banner message={message} onClear={() => setMessage(null)} /> : null}
      {classrooms.length === 0 ? (
        <p className="rounded-lg bg-white px-3 py-6 text-center text-sm text-neutral-600">Aucune classe attribuée.</p>
      ) : null}

      {tab === 'classe' && classrooms.length > 0 ? (
        <Panel className="min-w-0">
          <div className="flex flex-wrap items-center gap-2 border-b border-black/5 px-3 py-2">
            <select className={`${inputClass} w-auto`} value={selectedClassroom} onChange={(e) => setSelectedClassroom(e.target.value)}>
              {classrooms.map((classroom) => (
                <option key={classroom.id} value={classroom.id}>
                  {unitLabel(classroom.grade_level?.stage) === 'Groupe' ? 'Groupe ' : ''}
                  {classroom.name}
                </option>
              ))}
            </select>
          </div>
          {classFile ? (
            <ClassFilePanel
              schoolId={schoolId}
              auth={auth}
              file={classFile}
              staff={[]}
              terms={[]}
              classrooms={classrooms}
              busy={busy}
              readOnly
              canWriteGrades
              onBusy={setBusy}
              onMessage={setMessage}
              onReload={async () => {
                if (selectedClassroom) await loadClassFile(selectedClassroom)
              }}
            />
          ) : (
            <p className="px-3 py-6 text-sm text-neutral-600">Chargement du dossier…</p>
          )}
        </Panel>
      ) : null}

      {tab === 'appel' && classrooms.length > 0 ? (
        <Panel>
          <form onSubmit={saveAttendance}>
            <div className="flex flex-wrap items-center gap-2 border-b border-black/5 px-3 py-2">
              <select className={`${inputClass} w-auto`} value={selectedClassroom} onChange={(e) => setSelectedClassroom(e.target.value)}>
                {classrooms.map((classroom) => (
                  <option key={classroom.id} value={classroom.id}>
                    {classroom.name}
                  </option>
                ))}
              </select>
              <input className={`${inputClass} w-auto`} type="date" value={attendanceDate} onChange={(e) => setAttendanceDate(e.target.value)} />
              <input className={`${inputClass} max-w-[10rem]`} value={query} onChange={(e) => setQuery(e.target.value)} placeholder="Filtrer" />
              <button type="submit" disabled={busy || students.length === 0} className={`${btnPrimary} ml-auto`}>
                {busy ? '…' : 'Enregistrer'}
              </button>
            </div>
            {visibleStudents.length === 0 ? (
              <p className="px-3 py-6 text-sm text-neutral-600">Aucun élève.</p>
            ) : (
              <table className="w-full text-sm">
                <tbody>
                  {visibleStudents.map((row) => (
                    <tr key={row.enrollment_id} className="border-t border-black/5">
                      <td className="px-3 py-1.5 font-medium">
                        {row.person?.first_name} {row.person?.last_name}
                      </td>
                      <td className="px-3 py-1.5 text-right">
                        <AttendancePills
                          value={marks[row.enrollment_id] ?? 'present'}
                          onChange={(status) => setMarks({ ...marks, [row.enrollment_id]: status })}
                        />
                      </td>
                    </tr>
                  ))}
                </tbody>
              </table>
            )}
          </form>
        </Panel>
      ) : null}

      {tab === 'kits' && classrooms.length > 0 ? (
        <KitsPanel
          schoolId={schoolId}
          auth={auth}
          yearId={yearId}
          years={years}
          grades={classrooms
            .map((row) => ({
              id: row.grade_level?.id ?? row.grade_level_id,
              name: row.grade_level?.name ?? row.name,
            }))
            .filter((row, index, list) => row.id && list.findIndex((item) => item.id === row.id) === index)}
          busy={busy}
          onBusy={setBusy}
          onMessage={setMessage}
          canManageOrders={false}
          lockedGradeId={currentClass?.grade_level?.id ?? currentClass?.grade_level_id}
        />
      ) : null}

      {currentClass && tab === 'appel' ? <p className="mt-2 text-[11px] text-neutral-500">Appel de {currentClass.name} — professeur de la classe uniquement.</p> : null}
    </main>
  )
}

function AttendancePills({ value, onChange }: { value: string; onChange: (status: string) => void }) {
  const options = [
    { id: 'present', label: 'P' },
    { id: 'absent', label: 'A' },
    { id: 'late', label: 'R' },
    { id: 'excused', label: 'E' },
  ]
  return (
    <div className="inline-flex gap-0.5" role="group" aria-label="Présence">
      {options.map((option) => {
        const active = value === option.id
        const tone =
          option.id === 'present'
            ? 'bg-fanabe-leaf text-white'
            : option.id === 'absent'
              ? 'bg-fanabe-clay text-white'
              : option.id === 'late'
                ? 'bg-fanabe-gold text-white'
                : 'bg-neutral-700 text-white'
        return (
          <button
            key={option.id}
            type="button"
            title={attendanceLabel(option.id)}
            aria-label={attendanceLabel(option.id)}
            className={`h-7 w-7 rounded text-xs font-semibold ${active ? tone : 'bg-black/5 text-neutral-600'}`}
            onClick={() => onChange(option.id)}
          >
            {option.label}
          </button>
        )
      })}
    </div>
  )
}

function StudentScreen({ session }: { session: Session }) {
  const [overview, setOverview] = useState<StudentOverview | null>(null)
  const [message, setMessage] = useState<string | null>(null)

  useEffect(() => {
    api<StudentOverview>('/api/v1/student/me', { token: session.token })
      .then(setOverview)
      .catch((error: Error) => setMessage(error.message))
  }, [session.token])

  return (
    <main className="mx-auto max-w-2xl px-3 py-3">
      {message ? <Banner message={message} onClear={() => setMessage(null)} /> : null}
      <p className="text-sm text-neutral-600">
        {session.person.first_name} · lecture seule
      </p>
      {overview ? (
        <div className="mt-3 space-y-3">
          <div className="grid grid-cols-3 overflow-hidden rounded-lg border border-black/8">
            <Kpi label="Classe" value={overview.enrollment?.classroom?.name ?? '—'} hint={overview.enrollment?.school?.name ?? ''} />
            <Kpi label="Présences" value={String(overview.attendance.length)} hint="14 jours" />
            <Kpi label="Reste" value={formatAr(overview.finance.remaining_amount)} hint={overview.finance.invoice?.number ?? '—'} />
          </div>
          <Panel>
            <h2 className="border-b border-black/5 px-3 py-2 text-sm font-semibold">Présence</h2>
            {overview.attendance.length === 0 ? (
              <p className="px-3 py-3 text-sm text-neutral-600">Rien sur les 14 derniers jours.</p>
            ) : (
              <ul className="divide-y divide-black/5 text-sm">
                {overview.attendance.map((row) => (
                  <li key={row.id} className="flex justify-between px-3 py-1.5">
                    <span>{formatDate(row.date)}</span>
                    <span className="font-medium">{attendanceLabel(row.status)}</span>
                  </li>
                ))}
              </ul>
            )}
          </Panel>
          <Panel className="p-3">
            <h2 className="text-sm font-semibold">Écolage</h2>
            {overview.finance.invoice ? (
              <ul className="mt-2 space-y-1 text-sm">
                {overview.finance.invoice.installments.map((row) => (
                  <li key={row.id} className="flex justify-between">
                    <span>{formatDate(row.due_on)}</span>
                    <span>{formatAr(row.remaining_amount)}</span>
                  </li>
                ))}
              </ul>
            ) : (
              <p className="mt-2 text-sm text-neutral-600">Pas encore de facture.</p>
            )}
          </Panel>
        </div>
      ) : null}
    </main>
  )
}

function FamilyEditor({
  family,
  schoolId,
  yearId,
  classrooms,
  auth,
  busy,
  onBusy,
  onMessage,
  onInvitation,
  onReload,
  onClose,
}: {
  family: FamilyRow
  schoolId: string
  yearId: string
  classrooms: ClassroomRow[]
  auth: { token: string }
  busy: boolean
  onBusy: (value: boolean) => void
  onMessage: (value: string | null) => void
  onInvitation: (value: string | null) => void
  onReload: () => Promise<void>
  onClose: () => void
}) {
  const [label, setLabel] = useState(family.label)
  const [drafts, setDrafts] = useState<Record<string, { first_name: string; last_name: string; phone: string }>>({})
  const [sibling, setSibling] = useState({
    first_name: '',
    last_name: family.members.find((m) => m.role_in_family === 'child')?.last_name ?? '',
    birth_date: '',
    classroom_id: '',
  })
  const [adult, setAdult] = useState({ first_name: '', last_name: '', phone: '', relationship: 'parent_of' })

  useEffect(() => {
    setLabel(family.label)
    setDrafts(
      Object.fromEntries(
        family.members.map((member) => [
          memberPersonId(member),
          {
            first_name: member.first_name ?? '',
            last_name: member.last_name ?? '',
            phone: member.phone_e164 ?? '',
          },
        ]),
      ),
    )
  }, [family])

  async function saveLabel(event: FormEvent) {
    event.preventDefault()
    onBusy(true)
    onMessage(null)
    try {
      await api(`/api/v1/schools/${schoolId}/families/${family.id}`, {
        ...auth,
        method: 'PATCH',
        body: JSON.stringify({ label }),
      })
      onMessage('Foyer mis à jour.')
      await onReload()
    } catch (error) {
      onMessage(error instanceof Error ? error.message : 'Foyer impossible à modifier.')
    } finally {
      onBusy(false)
    }
  }

  async function saveMember(member: FamilyMemberRow) {
    const id = memberPersonId(member)
    const draft = drafts[id]
    if (!draft) return
    onBusy(true)
    onMessage(null)
    try {
      await api(`/api/v1/schools/${schoolId}/families/${family.id}/members/${id}`, {
        ...auth,
        method: 'PATCH',
        body: JSON.stringify({
          first_name: draft.first_name,
          last_name: draft.last_name,
          phone: member.role_in_family === 'adult' ? draft.phone || null : undefined,
        }),
      })
      onMessage(`${draft.first_name} ${draft.last_name} enregistré.`)
      await onReload()
    } catch (error) {
      onMessage(error instanceof Error ? error.message : 'Personne impossible à modifier.')
    } finally {
      onBusy(false)
    }
  }

  async function addSibling(event: FormEvent) {
    event.preventDefault()
    onBusy(true)
    onMessage(null)
    try {
      await api(`/api/v1/schools/${schoolId}/families/${family.id}/children`, {
        ...auth,
        method: 'POST',
        body: JSON.stringify({
          school_year_id: yearId,
          first_name: sibling.first_name,
          last_name: sibling.last_name,
          birth_date: sibling.birth_date || undefined,
          classroom_id: sibling.classroom_id || undefined,
        }),
      })
      setSibling({ first_name: '', last_name: sibling.last_name, birth_date: '', classroom_id: sibling.classroom_id })
      onMessage('Fratrie inscrite.')
      await onReload()
    } catch (error) {
      onMessage(error instanceof Error ? error.message : 'Enfant impossible à ajouter.')
    } finally {
      onBusy(false)
    }
  }

  async function addAdult(event: FormEvent) {
    event.preventDefault()
    onBusy(true)
    onMessage(null)
    onInvitation(null)
    try {
      const created = await api<{ invitation_code: string | null }>(`/api/v1/schools/${schoolId}/families/${family.id}/adults`, {
        ...auth,
        method: 'POST',
        body: JSON.stringify(adult),
      })
      setAdult({ first_name: '', last_name: '', phone: '', relationship: 'parent_of' })
      if (created.invitation_code) {
        onInvitation(created.invitation_code)
        onMessage('Adulte ajouté. Remettez le code d’invitation.')
      } else {
        onMessage('Personne autorisée à récupérer l’enfant — pas d’accès à l’espace famille.')
      }
      await onReload()
    } catch (error) {
      onMessage(error instanceof Error ? error.message : 'Adulte impossible à ajouter.')
    } finally {
      onBusy(false)
    }
  }

  async function reissue(personId: string) {
    onBusy(true)
    onMessage(null)
    onInvitation(null)
    try {
      const payload = await api<{ invitation_code: string }>(`/api/v1/schools/${schoolId}/families/${family.id}/invite`, {
        ...auth,
        method: 'POST',
        body: JSON.stringify({ person_id: personId }),
      })
      onInvitation(payload.invitation_code)
      onMessage('Nouveau code d’invitation émis.')
      await onReload()
    } catch (error) {
      onMessage(error instanceof Error ? error.message : 'Invitation impossible à réémettre.')
    } finally {
      onBusy(false)
    }
  }

  return (
    <Panel className="min-w-0">
      <div className="flex items-center gap-2 border-b border-black/5 px-3 py-2">
        <button type="button" className={btnGhost} onClick={onClose}>
          Retour
        </button>
        <h2 className="text-sm font-semibold">{family.label}</h2>
      </div>
      <div className="space-y-4 p-3">
        <form onSubmit={saveLabel} className="flex flex-wrap items-end gap-2">
          <div className="min-w-[12rem] flex-1">
            <Field label="Nom du foyer">
              <input className={inputClass} value={label} onChange={(e) => setLabel(e.target.value)} />
            </Field>
          </div>
          <button type="submit" disabled={busy} className={btnPrimary}>
            Enregistrer
          </button>
        </form>
        <div>
          <h3 className="text-xs font-semibold uppercase tracking-wide text-neutral-500">Membres</h3>
          <ul className="mt-2 divide-y divide-black/5">
            {family.members.map((member) => {
              const id = memberPersonId(member)
              const draft = drafts[id] ?? { first_name: '', last_name: '', phone: '' }
              const types = (member.relationship_types ?? []).map(relationshipLabel).filter(Boolean)
              const canInvite =
                member.role_in_family === 'adult' &&
                !member.has_account &&
                (member.relationship_types ?? []).some((type) =>
                  ['parent_of', 'guardian_of', 'financial_contact_for'].includes(type),
                )
              return (
                <li key={id} className="py-2">
                  <div className="grid gap-2 sm:grid-cols-[1fr_1fr_auto]">
                    <input
                      className={inputClass}
                      value={draft.first_name}
                      onChange={(e) => setDrafts({ ...drafts, [id]: { ...draft, first_name: e.target.value } })}
                    />
                    <input
                      className={inputClass}
                      value={draft.last_name}
                      onChange={(e) => setDrafts({ ...drafts, [id]: { ...draft, last_name: e.target.value } })}
                    />
                    <button type="button" className={btnPrimary} disabled={busy} onClick={() => void saveMember(member)}>
                      Sauver
                    </button>
                  </div>
                  {member.role_in_family === 'adult' ? (
                    <input
                      className={`${inputClass} mt-2`}
                      value={draft.phone}
                      onChange={(e) => setDrafts({ ...drafts, [id]: { ...draft, phone: e.target.value } })}
                      placeholder="Téléphone"
                    />
                  ) : null}
                  <div className="mt-1 flex flex-wrap items-center gap-2 text-[11px] text-neutral-500">
                    <span>{member.role_in_family === 'child' ? 'Élève' : 'Adulte'}</span>
                    {types.length > 0 ? <span>{types.join(' · ')}</span> : null}
                    {member.public_id ? <span className="font-mono">{member.public_id}</span> : null}
                    {member.has_account ? <span>Compte actif</span> : null}
                    {member.invitation_pending ? <span>Invitation en cours</span> : null}
                    {canInvite ? (
                      <button type="button" className="underline" disabled={busy} onClick={() => void reissue(id)}>
                        Réémettre l’invitation
                      </button>
                    ) : null}
                  </div>
                </li>
              )
            })}
          </ul>
        </div>
        <form onSubmit={addSibling} className="space-y-2 rounded-md bg-black/[0.03] p-2">
          <h3 className="text-xs font-semibold uppercase tracking-wide text-neutral-500">Ajouter un enfant</h3>
          <div className="grid gap-2 sm:grid-cols-2">
            <input className={inputClass} value={sibling.first_name} onChange={(e) => setSibling({ ...sibling, first_name: e.target.value })} placeholder="Prénom" required />
            <input className={inputClass} value={sibling.last_name} onChange={(e) => setSibling({ ...sibling, last_name: e.target.value })} placeholder="Nom" required />
            <input className={inputClass} type="date" value={sibling.birth_date} onChange={(e) => setSibling({ ...sibling, birth_date: e.target.value })} />
            <select className={inputClass} value={sibling.classroom_id} onChange={(e) => setSibling({ ...sibling, classroom_id: e.target.value })} required>
              <option value="">Classe</option>
              {classrooms
                .filter((classroom) => !classroom.school_year_id || classroom.school_year_id === yearId)
                .map((classroom) => (
                  <option key={classroom.id} value={classroom.id}>
                    {classroomLabel(classroom)}
                  </option>
                ))}
            </select>
          </div>
          <button type="submit" disabled={busy || !yearId || sibling.classroom_id === ''} className={btnPrimary}>
            Inscrire la fratrie
          </button>
        </form>
        <form onSubmit={addAdult} className="space-y-2 rounded-md bg-black/[0.03] p-2">
          <h3 className="text-xs font-semibold uppercase tracking-wide text-neutral-500">Ajouter un adulte</h3>
          <div className="grid gap-2 sm:grid-cols-2">
            <input className={inputClass} value={adult.first_name} onChange={(e) => setAdult({ ...adult, first_name: e.target.value })} placeholder="Prénom" required />
            <input className={inputClass} value={adult.last_name} onChange={(e) => setAdult({ ...adult, last_name: e.target.value })} placeholder="Nom" required />
          </div>
          <input className={inputClass} value={adult.phone} onChange={(e) => setAdult({ ...adult, phone: e.target.value })} placeholder="Téléphone" />
          <select className={inputClass} value={adult.relationship} onChange={(e) => setAdult({ ...adult, relationship: e.target.value })}>
            <option value="parent_of">Parent — espace famille</option>
            <option value="guardian_of">Tuteur — espace famille</option>
            <option value="financial_contact_for">Contact financier — écolage seulement</option>
            <option value="pickup_authorized_for">Autorisé à récupérer — pas d’espace famille</option>
          </select>
          <button type="submit" disabled={busy} className={btnPrimary}>
            Ajouter
          </button>
        </form>
      </div>
    </Panel>
  )
}

function ParentScreen({ session, tab }: { session: Session; tab: ParentTab }) {
  const auth = useMemo(() => ({ token: session.token }), [session.token])
  const [children, setChildren] = useState<PersonRow[]>([])
  const [finances, setFinances] = useState<Record<string, ChildFinance>>({})
  const [attendance, setAttendance] = useState<Record<string, AttendanceRow[]>>({})
  const [inbox, setInbox] = useState<ParentInboxMessage[]>([])
  const [kits, setKits] = useState<{
    children: Array<{ person_id: string; enrollment_id: string; first_name: string; last_name: string; grade_level_id?: string | null }>
    catalog: KitDefinitionRow[]
    orders: KitOrderRow[]
  }>({ children: [], catalog: [], orders: [] })
  const [bulletins, setBulletins] = useState<Record<string, BulletinRow>>({})
  const [certificates, setCertificates] = useState<Record<string, Array<{ id: string; type_label: string; public_reference: string; status: string }>>>({})
  const [consents, setConsents] = useState<ConsentRow[]>([])
  const [linkRequests, setLinkRequests] = useState<LinkRequestRow[]>([])
  const [transfers, setTransfers] = useState<TransferRow[]>([])
  const [accessLog, setAccessLog] = useState<AccessLogRow[]>([])
  const [message, setMessage] = useState<string | null>(null)
  const [busy, setBusy] = useState(false)
  const [editing, setEditing] = useState<Record<string, { first_name: string; last_name: string }>>({})
  const [shareChildIds, setShareChildIds] = useState<string[]>([])
  const [shareToken, setShareToken] = useState<string | null>(null)
  const [consentForm, setConsentForm] = useState({ childId: '', schoolId: '', scope: 'academic.records', purpose: 'Partage avec l’école' })

  async function loadFamily() {
    const payload = await api<{ data: PersonRow[] }>('/api/v1/parent/children', auth)
    setChildren(payload.data)
    setEditing(
      Object.fromEntries(payload.data.map((child) => [child.id, { first_name: child.first_name, last_name: child.last_name }])),
    )
    setShareChildIds(payload.data.filter((child) => child.access === 'guardian').map((child) => child.id))
    const firstGuardian = payload.data.find((child) => child.access === 'guardian')
    const schoolId = firstGuardian?.enrollments?.[0]?.school_id ?? payload.data[0]?.enrollments?.[0]?.school_id ?? ''
    setConsentForm((prev) => ({
      ...prev,
      childId: prev.childId || firstGuardian?.id || payload.data[0]?.id || '',
      schoolId: prev.schoolId || schoolId,
    }))
    const [entries, presence, notes, kitPayload, bulletinEntries, certEntries] = await Promise.all([
      Promise.all(
        payload.data.map(async (child) => {
          const finance = await api<ChildFinance>(`/api/v1/parent/children/${child.id}/finance`, auth)
          return [child.id, finance] as const
        }),
      ),
      Promise.all(
        payload.data
          .filter((child) => child.access === 'guardian')
          .map(async (child) => {
            const rows = await api<{ data: AttendanceRow[] }>(`/api/v1/parent/children/${child.id}/attendance`, auth)
            return [child.id, rows.data] as const
          }),
      ),
      api<{ data: ParentInboxMessage[] }>('/api/v1/parent/messages', auth),
      api<{
        children: Array<{ person_id: string; enrollment_id: string; first_name: string; last_name: string; grade_level_id?: string | null }>
        catalog: KitDefinitionRow[]
        orders: KitOrderRow[]
      }>('/api/v1/parent/kits', auth),
      Promise.all(
        payload.data
          .filter((child) => child.access === 'guardian')
          .map(async (child) => {
            const row = await api<{ data: BulletinRow }>(`/api/v1/parent/children/${child.id}/bulletin`, auth)
            return [child.id, row.data] as const
          }),
      ),
      Promise.all(
        payload.data
          .filter((child) => child.access === 'guardian')
          .map(async (child) => {
            const row = await api<{ data: Array<{ id: string; type_label: string; public_reference: string; status: string }> }>(
              `/api/v1/parent/children/${child.id}/certificates`,
              auth,
            )
            return [child.id, row.data] as const
          }),
      ),
    ])
    setFinances(Object.fromEntries(entries))
    setAttendance(Object.fromEntries(presence))
    setInbox(notes.data)
    setKits(kitPayload)
    setBulletins(Object.fromEntries(bulletinEntries))
    setCertificates(Object.fromEntries(certEntries))
  }

  async function loadAccount() {
    const [consentList, requests, transferList, log] = await Promise.all([
      api<{ data: ConsentRow[] }>('/api/v1/parent/consents', auth),
      api<{ data: LinkRequestRow[] }>('/api/v1/parent/link-requests', auth),
      api<{ data: TransferRow[] }>('/api/v1/parent/transfers', auth),
      api<{ data: AccessLogRow[] }>('/api/v1/parent/access-log', auth),
    ])
    setConsents(consentList.data)
    setLinkRequests(requests.data)
    setTransfers(transferList.data)
    setAccessLog(log.data)
  }

  useEffect(() => {
    loadFamily().catch((error: Error) => setMessage(error.message))
  }, [session.token])

  useEffect(() => {
    if (tab !== 'compte') return
    loadAccount().catch((error: Error) => setMessage(error.message))
  }, [tab, session.token])

  const totalRemaining = Object.values(finances).reduce((sum, row) => sum + row.remaining_amount, 0)
  const guardians = children.filter((child) => child.access === 'guardian')

  async function saveChild(childId: string) {
    const draft = editing[childId]
    if (!draft) return
    setBusy(true)
    setMessage(null)
    try {
      await api(`/api/v1/parent/children/${childId}`, {
        ...auth,
        method: 'PATCH',
        body: JSON.stringify(draft),
      })
      setMessage('État civil mis à jour.')
      await loadFamily()
    } catch (error) {
      setMessage(error instanceof Error ? error.message : 'Modification impossible.')
    } finally {
      setBusy(false)
    }
  }

  async function createShareToken(event: FormEvent) {
    event.preventDefault()
    setBusy(true)
    setMessage(null)
    try {
      const payload = await api<{ token: string }>('/api/v1/parent/share-tokens', {
        ...auth,
        method: 'POST',
        body: JSON.stringify({ child_person_ids: shareChildIds }),
      })
      setShareToken(payload.token)
      setMessage('Lien créé. Remettez-le à l’école d’accueil.')
    } catch (error) {
      setMessage(error instanceof Error ? error.message : 'Lien impossible à créer.')
    } finally {
      setBusy(false)
    }
  }

  async function resolveLink(id: string, action: 'approve' | 'refuse') {
    setBusy(true)
    setMessage(null)
    try {
      await api(`/api/v1/parent/link-requests/${id}/${action}`, { ...auth, method: 'POST', body: JSON.stringify({}) })
      setMessage(action === 'approve' ? 'Rattachement accepté.' : 'Rattachement refusé.')
      await loadAccount()
    } catch (error) {
      setMessage(error instanceof Error ? error.message : 'Demande impossible à traiter.')
    } finally {
      setBusy(false)
    }
  }

  async function resolveTransfer(id: string, action: 'approve' | 'refuse') {
    setBusy(true)
    setMessage(null)
    try {
      await api(`/api/v1/parent/transfers/${id}/${action}`, { ...auth, method: 'POST', body: JSON.stringify({}) })
      setMessage(action === 'approve' ? 'Transfert accepté.' : 'Transfert refusé.')
      await loadAccount()
    } catch (error) {
      setMessage(error instanceof Error ? error.message : 'Transfert impossible à traiter.')
    } finally {
      setBusy(false)
    }
  }

  async function grantConsent(event: FormEvent) {
    event.preventDefault()
    setBusy(true)
    setMessage(null)
    try {
      await api('/api/v1/parent/consents', {
        ...auth,
        method: 'POST',
        body: JSON.stringify({
          subject_person_id: consentForm.childId,
          grantee_school_id: consentForm.schoolId,
          scope: consentForm.scope,
          purpose: consentForm.purpose,
        }),
      })
      setMessage('Consentement enregistré.')
      await loadAccount()
    } catch (error) {
      setMessage(error instanceof Error ? error.message : 'Consentement impossible à enregistrer.')
    } finally {
      setBusy(false)
    }
  }

  async function revokeConsent(id: string) {
    setBusy(true)
    setMessage(null)
    try {
      await api(`/api/v1/parent/consents/${id}/revoke`, { ...auth, method: 'POST', body: JSON.stringify({}) })
      setMessage('Consentement révoqué.')
      await loadAccount()
    } catch (error) {
      setMessage(error instanceof Error ? error.message : 'Révocation impossible.')
    } finally {
      setBusy(false)
    }
  }

  return (
    <main className="mx-auto max-w-2xl px-3 py-3">
      {message ? <Banner message={message} onClear={() => setMessage(null)} /> : null}

      {tab === 'enfants' ? (
        <div className="space-y-3">
          <div className="flex items-end justify-between gap-3">
            <p className="text-sm text-neutral-600">{session.person.first_name}</p>
            <p className={`text-sm font-semibold ${totalRemaining > 0 ? 'text-fanabe-clay' : 'text-fanabe-leaf'}`}>
              {formatAr(totalRemaining)}
            </p>
          </div>
          <ul className="space-y-2">
            {children.map((child) => {
              const finance = finances[child.id]
              const presence = attendance[child.id] ?? []
              const draft = editing[child.id]
              const canEdit = child.access === 'guardian'
              return (
                <li key={child.id}>
                  <Panel className="p-3">
                    <div className="flex items-baseline justify-between gap-2">
                      {canEdit && draft ? (
                        <div className="flex min-w-0 flex-1 flex-wrap gap-1">
                          <input
                            className={`${inputClass} max-w-[8rem]`}
                            value={draft.first_name}
                            onChange={(e) => setEditing({ ...editing, [child.id]: { ...draft, first_name: e.target.value } })}
                          />
                          <input
                            className={`${inputClass} max-w-[8rem]`}
                            value={draft.last_name}
                            onChange={(e) => setEditing({ ...editing, [child.id]: { ...draft, last_name: e.target.value } })}
                          />
                          <button type="button" className={btnGhost} disabled={busy} onClick={() => void saveChild(child.id)}>
                            Sauver
                          </button>
                        </div>
                      ) : (
                        <p className="text-sm font-semibold">
                          {child.first_name} {child.last_name}
                        </p>
                      )}
                      {finance ? (
                        <p className={`text-sm font-semibold ${finance.remaining_amount > 0 ? 'text-fanabe-clay' : 'text-fanabe-leaf'}`}>
                          {formatAr(finance.remaining_amount)}
                        </p>
                      ) : null}
                    </div>
                    {child.access === 'finance' ? (
                      <p className="mt-1 text-[11px] text-neutral-500">Contact financier — écolage seulement.</p>
                    ) : null}
                    {finance?.data.map((row, index) => (
                      <div key={index} className="mt-2 text-xs text-neutral-600">
                        <p>
                          {row.school?.name}
                          {row.classroom ? ` · ${row.classroom.name}` : ''}
                        </p>
                        {row.invoice ? (
                          <ul className="mt-1 space-y-0.5">
                            {row.invoice.installments.map((item) => (
                              <li key={item.id} className="flex justify-between">
                                <span>{formatDate(item.due_on)}</span>
                                <span>{formatAr(item.remaining_amount)}</span>
                              </li>
                            ))}
                          </ul>
                        ) : (
                          <p className="mt-1">Pas encore de facture.</p>
                        )}
                      </div>
                    ))}
                    {canEdit ? (
                      <div className="mt-3">
                        <h3 className="text-[11px] font-semibold uppercase tracking-wide text-neutral-500">Présence</h3>
                        {presence.length === 0 ? (
                          <p className="mt-1 text-xs text-neutral-600">Rien sur les 14 derniers jours.</p>
                        ) : (
                          <ul className="mt-1 space-y-0.5 text-xs">
                            {presence.map((row) => (
                              <li key={row.id} className="flex justify-between">
                                <span>{formatDate(row.date)}</span>
                                <span>{attendanceLabel(row.status)}</span>
                              </li>
                            ))}
                          </ul>
                        )}
                        <h3 className="mt-3 text-[11px] font-semibold uppercase tracking-wide text-neutral-500">Notes</h3>
                        {bulletins[child.id]?.overall_average != null ? (
                          <p className="mt-1 text-xs text-neutral-600">
                            Moyenne : <strong>{bulletins[child.id].overall_average}</strong>
                          </p>
                        ) : (
                          <p className="mt-1 text-xs text-neutral-600">Aucune note pour le moment.</p>
                        )}
                        {(bulletins[child.id]?.subjects ?? []).length > 0 ? (
                          <ul className="mt-1 space-y-0.5 text-xs text-neutral-600">
                            {bulletins[child.id].subjects?.map((subject) => (
                              <li key={subject.subject ?? 'matiere'} className="flex justify-between gap-2">
                                <span>{subject.subject ?? 'Matière'}</span>
                                <span className="tabular-nums">{subject.average ?? '—'}</span>
                              </li>
                            ))}
                          </ul>
                        ) : null}
                        <h3 className="mt-3 text-[11px] font-semibold uppercase tracking-wide text-neutral-500">Documents</h3>
                        {(certificates[child.id] ?? []).length === 0 ? (
                          <p className="mt-1 text-xs text-neutral-600">Aucun document émis.</p>
                        ) : (
                          <ul className="mt-1 space-y-0.5 text-xs text-neutral-600">
                            {certificates[child.id].map((row) => (
                              <li key={row.id}>
                                {row.type_label} · {row.public_reference} · {certificateStatusLabel(row.status)}
                              </li>
                            ))}
                          </ul>
                        )}
                      </div>
                    ) : null}
                  </Panel>
                </li>
              )
            })}
          </ul>
          {children.length === 0 && !message ? (
            <p className="mt-6 text-center text-sm text-neutral-600">Aucun enfant rattaché. Demandez un code à l’école.</p>
          ) : null}
        </div>
      ) : null}

      {tab === 'kits' ? (
        <div className="space-y-3">
          <Panel className="p-3">
            <h2 className="text-sm font-semibold">Fournitures</h2>
            <p className="mt-1 text-xs text-neutral-500">
              Liste du niveau pour l’année. Commander une gamme chez le partenaire, ou fournir les articles vous-même.
              FANABE n’encaisse pas.
            </p>
            {kits.catalog.map((definition) => {
              const child =
                kits.children.find((row) => row.grade_level_id && row.grade_level_id === definition.grade_level_id) ??
                kits.children[0]
              const already = kits.orders.some(
                (row) =>
                  row.status !== 'cancelled' &&
                  row.enrollment_id === child?.enrollment_id &&
                  (row.kit_definition_id == null || row.kit_definition_id === definition.id),
              )
              return (
                <div key={definition.id} className="mt-3 border-t border-black/5 pt-2">
                  <p className="text-sm font-medium">
                    {definition.name}
                    {definition.grade_level ? <span className="ml-2 text-xs font-normal text-neutral-500">{definition.grade_level}</span> : null}
                  </p>
                  <p className="mt-0.5 text-[11px] text-neutral-500">
                    {definition.price_source_label}
                    {definition.packs[0]?.supplier?.name ? ` · ${definition.packs[0].supplier.name}` : ''}
                  </p>
                  <KitSupplyTable definition={definition} />
                  {already ? (
                    <p className="mt-2 text-xs text-neutral-500">Un choix est déjà enregistré pour cet élève.</p>
                  ) : (
                    <div className="mt-2 flex flex-wrap gap-1">
                      {definition.packs.map((pack) => (
                        <button
                          key={pack.id}
                          type="button"
                          className={btnGhost}
                          disabled={busy || !child}
                          onClick={() => {
                            if (!child) return
                            setBusy(true)
                            setMessage(null)
                            api('/api/v1/parent/kit-orders', {
                              ...auth,
                              method: 'POST',
                              body: JSON.stringify({
                                enrollment_id: child.enrollment_id,
                                fulfillment: 'partner',
                                kit_pack_id: pack.id,
                              }),
                            })
                              .then(() => loadFamily())
                              .then(() => setMessage(`Commande ${pack.tier_label} pour ${child.first_name}. ${pack.pay_instruction}`))
                              .catch((error: Error) => setMessage(error.message))
                              .finally(() => setBusy(false))
                          }}
                        >
                          Commander {pack.tier_label} · {formatAr(pack.total_amount)}
                        </button>
                      ))}
                      <button
                        type="button"
                        className={btnGhost}
                        disabled={busy || !child}
                        onClick={() => {
                          if (!child) return
                          setBusy(true)
                          setMessage(null)
                          api('/api/v1/parent/kit-orders', {
                            ...auth,
                            method: 'POST',
                            body: JSON.stringify({
                              enrollment_id: child.enrollment_id,
                              fulfillment: 'self',
                              kit_definition_id: definition.id,
                            }),
                          })
                            .then(() => loadFamily())
                            .then(() => setMessage(`Vous fournissez la liste pour ${child.first_name}.`))
                            .catch((error: Error) => setMessage(error.message))
                            .finally(() => setBusy(false))
                        }}
                      >
                        Je fournis moi-même
                      </button>
                    </div>
                  )}
                </div>
              )
            })}
            {kits.catalog.length === 0 ? <p className="mt-3 text-sm text-neutral-600">Aucune liste de fournitures pour le moment.</p> : null}
          </Panel>
          {kits.orders.length > 0 ? (
            <Panel>
              <h2 className="border-b border-black/5 px-3 py-2 text-sm font-semibold">Vos choix</h2>
              <ul className="divide-y divide-black/5 text-sm">
                {kits.orders.map((row) => (
                  <li key={row.id} className="px-3 py-2">
                    <p className="font-medium">
                      {row.student_name}
                      {row.total_amount > 0 ? ` · ${formatAr(row.total_amount)}` : ''}
                    </p>
                    <p className="text-xs text-neutral-500">
                      {row.status_label ?? row.status} · {row.pay_instruction}
                    </p>
                  </li>
                ))}
              </ul>
            </Panel>
          ) : null}
        </div>
      ) : null}

      {tab === 'messages' ? (
        <Panel>
          <h2 className="border-b border-black/5 px-3 py-2 text-sm font-semibold">Messages</h2>
          {inbox.length === 0 ? (
            <p className="px-3 py-3 text-sm text-neutral-600">Aucun message pour le moment.</p>
          ) : (
            <ul className="divide-y divide-black/5">
              {inbox.map((note) => (
                <li key={note.id} className="px-3 py-2">
                  <p className="text-sm font-medium">{note.subject}</p>
                  <p className="mt-0.5 whitespace-pre-line text-xs text-neutral-600">{note.body}</p>
                </li>
              ))}
            </ul>
          )}
        </Panel>
      ) : null}

      {tab === 'compte' ? (
        <div className="space-y-3">
          <Panel className="p-3">
            <h2 className="text-sm font-semibold">Lien pour une autre école</h2>
            <p className="mt-1 text-xs text-neutral-500">Le jeton ne donne accès qu’aux enfants que vous autorisez.</p>
            <form onSubmit={createShareToken} className="mt-2 space-y-2">
              {guardians.map((child) => (
                <label key={child.id} className="flex items-center gap-2 text-sm">
                  <input
                    type="checkbox"
                    checked={shareChildIds.includes(child.id)}
                    onChange={(event) => {
                      setShareChildIds((prev) =>
                        event.target.checked ? [...prev, child.id] : prev.filter((id) => id !== child.id),
                      )
                    }}
                  />
                  {child.first_name} {child.last_name}
                </label>
              ))}
              <button type="submit" disabled={busy || shareChildIds.length === 0} className={btnPrimary}>
                Créer un lien
              </button>
            </form>
            {shareToken ? (
              <div className="mt-2 flex items-center justify-between gap-2 rounded-md bg-fanabe-mist px-2 py-2 text-xs">
                <strong className="break-all font-mono">{shareToken}</strong>
                <button type="button" className={btnGhost} onClick={() => copyText(shareToken)}>
                  Copier
                </button>
              </div>
            ) : null}
          </Panel>
          {linkRequests.length > 0 ? (
            <Panel className="p-3">
              <h2 className="text-sm font-semibold">Demandes de rattachement</h2>
              <ul className="mt-2 space-y-2 text-sm">
                {linkRequests.map((row) => (
                  <li key={row.id} className="flex items-center justify-between gap-2">
                    <span>{row.school_name ?? 'École'}</span>
                    <div className="flex gap-1">
                      <button type="button" className={btnPrimary} disabled={busy} onClick={() => void resolveLink(row.id, 'approve')}>
                        Accepter
                      </button>
                      <button type="button" className={btnGhost} disabled={busy} onClick={() => void resolveLink(row.id, 'refuse')}>
                        Refuser
                      </button>
                    </div>
                  </li>
                ))}
              </ul>
            </Panel>
          ) : null}
          <Panel className="p-3">
            <h2 className="text-sm font-semibold">Transferts</h2>
            {transfers.length === 0 ? (
              <p className="mt-2 text-xs text-neutral-600">Aucun transfert en cours.</p>
            ) : (
              <ul className="mt-2 space-y-2 text-sm">
                {transfers.map((row) => (
                  <li key={row.id}>
                    <p>
                      {row.person ? `${row.person.first_name} ${row.person.last_name}` : 'Élève'} · {transferLabel(row.status)}
                    </p>
                    <p className="text-xs text-neutral-600">
                      {row.origin_school} → {row.destination_school}
                    </p>
                    {row.status === 'pending_parent' ? (
                      <div className="mt-1 flex gap-1">
                        <button type="button" className={btnPrimary} disabled={busy} onClick={() => void resolveTransfer(row.id, 'approve')}>
                          Accepter
                        </button>
                        <button type="button" className={btnGhost} disabled={busy} onClick={() => void resolveTransfer(row.id, 'refuse')}>
                          Refuser
                        </button>
                      </div>
                    ) : null}
                  </li>
                ))}
              </ul>
            )}
          </Panel>
          <Panel className="p-3">
            <h2 className="text-sm font-semibold">Consentements</h2>
            <form onSubmit={grantConsent} className="mt-2 space-y-2">
              <select className={inputClass} value={consentForm.childId} onChange={(e) => setConsentForm({ ...consentForm, childId: e.target.value })}>
                {guardians.map((child) => (
                  <option key={child.id} value={child.id}>
                    {child.first_name} {child.last_name}
                  </option>
                ))}
              </select>
              <select className={inputClass} value={consentForm.scope} onChange={(e) => setConsentForm({ ...consentForm, scope: e.target.value })}>
                <option value="academic.records">Bulletins</option>
                <option value="academic.attendance">Présence</option>
                <option value="finance.history">Écolage</option>
                <option value="identity.core">Identité</option>
                <option value="identity.contact">Coordonnées</option>
              </select>
              <input className={inputClass} value={consentForm.purpose} onChange={(e) => setConsentForm({ ...consentForm, purpose: e.target.value })} required />
              <button type="submit" disabled={busy || !consentForm.childId || !consentForm.schoolId} className={btnPrimary}>
                Accorder
              </button>
            </form>
            <ul className="mt-3 space-y-1 text-xs">
              {consents.map((row) => (
                <li key={row.id} className="flex items-center justify-between gap-2">
                  <span>
                    {consentLabel(row.scope)} · {row.school_name ?? 'École'}
                    {row.active ? '' : ' · révoqué'}
                  </span>
                  {row.active ? (
                    <button type="button" className="underline" disabled={busy} onClick={() => void revokeConsent(row.id)}>
                      Révoquer
                    </button>
                  ) : null}
                </li>
              ))}
            </ul>
          </Panel>
          <Panel>
            <h2 className="border-b border-black/5 px-3 py-2 text-sm font-semibold">Journal d’accès</h2>
            {accessLog.length === 0 ? (
              <p className="px-3 py-3 text-sm text-neutral-600">Aucun événement.</p>
            ) : (
              <ul className="divide-y divide-black/5 text-xs">
                {accessLog.slice(0, 20).map((row) => (
                  <li key={row.id} className="flex justify-between gap-2 px-3 py-1.5">
                    <span>{row.action}</span>
                    <span className="text-neutral-500">{row.occurred_at ? new Date(row.occurred_at).toLocaleString('fr-FR') : ''}</span>
                  </li>
                ))}
              </ul>
            )}
          </Panel>
        </div>
      ) : null}
    </main>
  )
}
