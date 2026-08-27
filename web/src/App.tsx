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

type ClassroomRow = {
  id: string
  name: string
  grade_level_id: string
  grade_level?: { name: string }
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

type DirectionTab = 'accueil' | 'famille' | 'classe' | 'caisse' | 'indices'
type TeacherTab = 'classe' | 'appel'
type ParentTab = 'enfants' | 'messages' | 'compte'

const PAGE_SIZE = 40

const DIRECTION_NAV: Array<{ id: DirectionTab; label: string }> = [
  { id: 'accueil', label: 'Aujourd’hui' },
  { id: 'famille', label: 'Familles' },
  { id: 'classe', label: 'Classes' },
  { id: 'caisse', label: 'Caisse' },
  { id: 'indices', label: 'Indices' },
]

const TEACHER_NAV: Array<{ id: TeacherTab; label: string }> = [
  { id: 'appel', label: 'Appel' },
  { id: 'classe', label: 'Effectif' },
]

const PARENT_NAV: Array<{ id: ParentTab; label: string }> = [
  { id: 'enfants', label: 'Enfants' },
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
            <span className="hidden max-w-40 truncate text-xs text-white/55 sm:inline">{schoolName}</span>
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

function EnrollmentWizard({
  yearId,
  yearLabel,
  schoolId,
  auth,
  families,
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
  busy: boolean
  onBusy: (value: boolean) => void
  onClose: () => void
  onEnrolled: (result: { familyId: string; invitation: string | null }) => Promise<void>
}) {
  const [step, setStep] = useState<1 | 2 | 3 | 'done'>(1)
  const [error, setError] = useState<string | null>(null)
  const [student, setStudent] = useState({ first_name: '', last_name: '', birth_date: '', sex: 'unspecified' })
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
  const studentReady = student.first_name.trim() !== '' && student.last_name.trim() !== '' && student.birth_date !== ''
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
                <strong>{doneName}</strong> est inscrit{foyerMode === 'existing' ? ` au foyer ${chosenFamily?.label ?? ''}` : ` au foyer ${label || student.last_name}`}.
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
  const [classrooms, setClassrooms] = useState<ClassroomRow[]>([])
  const [grades, setGrades] = useState<Array<{ id: string; name: string }>>([])
  const [enrollments, setEnrollments] = useState<EnrollmentRow[]>([])
  const [message, setMessage] = useState<string | null>(null)
  const [invitation, setInvitation] = useState<string | null>(null)
  const [busy, setBusy] = useState(false)
  const [query, setQuery] = useState('')
  const [page, setPage] = useState(1)
  const [classFilter, setClassFilter] = useState('')
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

  async function loadCore() {
    const years = await api<{ data: Array<{ id: string; is_current: boolean; label: string }> }>(
      `/api/v1/schools/${schoolId}/years`,
      auth,
    )
    const current = years.data.find((year) => year.is_current) ?? years.data[0]
    setYearId(current?.id ?? '')
    setYearLabel(current?.label ?? '2026-2027')
    const [classList, gradeList, today] = await Promise.all([
      api<{ data: ClassroomRow[] }>(`/api/v1/schools/${schoolId}/classrooms`, auth),
      api<{ data: Array<{ id: string; name: string }> }>(`/api/v1/schools/${schoolId}/grade-levels`, auth),
      api<Cockpit>(`/api/v1/schools/${schoolId}/cockpit`, auth),
    ])
    setClassrooms(classList.data)
    setGrades(gradeList.data)
    setCockpit(today)
    setNewClassGrade((prev) => prev || gradeList.data[0]?.id || '')
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

  useEffect(() => {
    loadCore().catch((error: Error) => setMessage(error.message))
  }, [schoolId, session.token])

  useEffect(() => {
    if (tab === 'famille') {
      Promise.all([loadPeople(), loadFamilies(), loadTransfers()]).catch((error: Error) => setMessage(error.message))
    }
    if (tab === 'classe' || tab === 'caisse') {
      loadEnrollments().catch((error: Error) => setMessage(error.message))
    }
    if (tab === 'indices') {
      loadReliability().catch((error: Error) => setMessage(error.message))
    }
    setQuery('')
    setPage(1)
  }, [tab, schoolId, session.token])

  useEffect(() => {
    setPage(1)
  }, [query, classFilter])

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
      await api(`/api/v1/schools/${schoolId}/classrooms`, {
        ...auth,
        method: 'POST',
        body: JSON.stringify({ school_year_id: yearId, grade_level_id: newClassGrade, name: newClassName }),
      })
      setMessage(`Classe ${newClassName} créée.`)
      await loadCore()
    } catch (error) {
      setMessage(error instanceof Error ? error.message : 'Classe impossible à créer.')
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
            <form onSubmit={createClassroom} className="mt-3 space-y-2">
              <select className={inputClass} value={newClassGrade} onChange={(e) => setNewClassGrade(e.target.value)}>
                {grades.map((grade) => (
                  <option key={grade.id} value={grade.id}>
                    {grade.name}
                  </option>
                ))}
              </select>
              <input className={inputClass} value={newClassName} onChange={(e) => setNewClassName(e.target.value)} required />
              <button type="submit" disabled={busy || !yearId || !newClassGrade} className={btnBlock}>
                Créer
              </button>
            </form>
            <ul className="mt-3 divide-y divide-black/5 text-sm">
              {classrooms.map((classroom) => {
                const count = activeEnrollments.filter((row) => row.classroom_id === classroom.id).length
                return (
                  <li key={classroom.id}>
                    <button
                      type="button"
                      className={`flex w-full items-center justify-between py-1.5 text-left ${classFilter === classroom.id ? 'font-semibold text-fanabe-leaf' : ''}`}
                      onClick={() => setClassFilter((prev) => (prev === classroom.id ? '' : classroom.id))}
                    >
                      <span>{classroom.name}</span>
                      <span className="text-xs text-neutral-500">{count}</span>
                    </button>
                  </li>
                )
              })}
            </ul>
          </Panel>
          <Panel className="min-w-0">
            <div className="flex flex-wrap items-center gap-2 border-b border-black/5 px-3 py-2">
              <input className={`${inputClass} max-w-xs`} value={query} onChange={(e) => setQuery(e.target.value)} placeholder="Rechercher un élève" />
              <Pager page={page} total={filteredEnrollments.length} onPage={setPage} />
            </div>
            <div className="overflow-auto">
              <table className="w-full text-sm">
                <thead className="bg-black/[0.03] text-left text-[11px] uppercase tracking-wide text-neutral-500">
                  <tr>
                    <th className="px-3 py-1.5 font-medium">Élève</th>
                    <th className="px-3 py-1.5 font-medium">Classe</th>
                  </tr>
                </thead>
                <tbody>
                  {pagedEnrollments.map((row) => (
                    <tr key={row.id} className="border-t border-black/5">
                      <td className="px-3 py-1.5 font-medium">
                        {row.person?.first_name} {row.person?.last_name}
                      </td>
                      <td className="px-3 py-1.5">
                        <select
                          className={inputClass}
                          value={row.classroom_id ?? ''}
                          onChange={(event) => {
                            if (event.target.value) void assignClassroom(row.id, event.target.value)
                          }}
                        >
                          <option value="">Sans classe</option>
                          {classrooms.map((classroom) => (
                            <option key={classroom.id} value={classroom.id}>
                              {classroom.name}
                            </option>
                          ))}
                        </select>
                      </td>
                    </tr>
                  ))}
                </tbody>
              </table>
            </div>
          </Panel>
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
  const [students, setStudents] = useState<RosterStudent[]>([])
  const [query, setQuery] = useState('')
  const [attendanceDate, setAttendanceDate] = useState(() => new Date().toISOString().slice(0, 10))
  const [marks, setMarks] = useState<Record<string, string>>({})
  const [message, setMessage] = useState<string | null>(null)
  const [busy, setBusy] = useState(false)
  const auth = useMemo(() => ({ token: session.token }), [session.token])
  const currentClass = classrooms.find((row) => row.id === selectedClassroom)
  const visibleStudents = students.filter((row) =>
    matchesQuery(`${row.person?.first_name ?? ''} ${row.person?.last_name ?? ''} ${row.person?.public_id ?? ''}`, query),
  )

  async function refresh() {
    const classList = await api<{ data: ClassroomRow[] }>(`/api/v1/schools/${schoolId}/classrooms`, auth)
    setClassrooms(classList.data)
    setSelectedClassroom((prev) => prev || classList.data[0]?.id || '')
  }

  async function loadRoster(classroomId: string) {
    const payload = await api<{ data: { students: RosterStudent[] } }>(
      `/api/v1/schools/${schoolId}/classrooms/${classroomId}/roster`,
      auth,
    )
    setStudents(payload.data.students)
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
        <Panel>
          <div className="flex flex-wrap items-center gap-2 border-b border-black/5 px-3 py-2">
            <select className={`${inputClass} w-auto`} value={selectedClassroom} onChange={(e) => setSelectedClassroom(e.target.value)}>
              {classrooms.map((classroom) => (
                <option key={classroom.id} value={classroom.id}>
                  {classroom.name}
                </option>
              ))}
            </select>
            <input className={`${inputClass} max-w-xs`} value={query} onChange={(e) => setQuery(e.target.value)} placeholder="Filtrer l’effectif" />
            <span className="ml-auto text-xs text-neutral-500">{visibleStudents.length} élève(s)</span>
          </div>
          <table className="w-full text-sm">
            <tbody>
              {visibleStudents.map((row) => (
                <tr key={row.enrollment_id} className="border-t border-black/5">
                  <td className="px-3 py-1.5 font-medium">
                    {row.person?.first_name} {row.person?.last_name}
                  </td>
                  <td className="px-3 py-1.5 font-mono text-xs text-neutral-500">{row.person?.public_id}</td>
                </tr>
              ))}
            </tbody>
          </table>
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
  const [sibling, setSibling] = useState({ first_name: '', last_name: family.members.find((m) => m.role_in_family === 'child')?.last_name ?? '', birth_date: '' })
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
        }),
      })
      setSibling({ first_name: '', last_name: sibling.last_name, birth_date: '' })
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
          <div className="grid gap-2 sm:grid-cols-3">
            <input className={inputClass} value={sibling.first_name} onChange={(e) => setSibling({ ...sibling, first_name: e.target.value })} placeholder="Prénom" required />
            <input className={inputClass} value={sibling.last_name} onChange={(e) => setSibling({ ...sibling, last_name: e.target.value })} placeholder="Nom" required />
            <input className={inputClass} type="date" value={sibling.birth_date} onChange={(e) => setSibling({ ...sibling, birth_date: e.target.value })} />
          </div>
          <button type="submit" disabled={busy || !yearId} className={btnPrimary}>
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
    const [entries, presence, notes] = await Promise.all([
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
    ])
    setFinances(Object.fromEntries(entries))
    setAttendance(Object.fromEntries(presence))
    setInbox(notes.data)
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
