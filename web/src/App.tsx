import { useEffect, useMemo, useState, type FormEvent, type ReactNode } from 'react'
import { api, clearSession, loadSession, saveSession, type Session } from './session'

type PersonRow = {
  id: string
  public_id: string
  first_name: string
  last_name: string
  kind?: string
  phone_e164?: string
  email?: string
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

type ChildFinance = {
  remaining_amount: number
  data: Array<{
    school: { name: string } | null
    classroom: { name: string } | null
    invoice: InvoiceRow | null
    payments: Array<{ amount: number; received_on: string; receipt_number: string | null }>
  }>
}

type SchoolTab = 'accueil' | 'famille' | 'classe' | 'presence' | 'caisse'

const SCHOOL_NAV: Array<{ id: SchoolTab; label: string; hint: string }> = [
  { id: 'accueil', label: 'Aujourd’hui', hint: 'Vue du jour' },
  { id: 'famille', label: 'Familles', hint: 'Inscrire' },
  { id: 'classe', label: 'Classes', hint: 'Affecter' },
  { id: 'presence', label: 'Présence', hint: 'Faire l’appel' },
  { id: 'caisse', label: 'Caisse', hint: 'Encaisser' },
]

export default function App() {
  const [session, setSession] = useState<Session | null>(() => loadSession())
  const [view, setView] = useState<'school' | 'parent'>(() => {
    const current = loadSession()
    return current?.schools.length ? 'school' : 'parent'
  })

  function signedIn(next: Session) {
    const schoolId = next.schoolId ?? next.schools[0]?.id
    const stored = { ...next, schoolId }
    saveSession(stored)
    setSession(stored)
    setView(stored.schools.length ? 'school' : 'parent')
  }

  function signOut() {
    clearSession()
    setSession(null)
  }

  if (!session) {
    return <LoginScreen onSuccess={signedIn} />
  }

  const schoolName = session.schools.find((row) => row.id === session.schoolId)?.name ?? session.schools[0]?.name
  const canSchool = session.schools.length > 0
  const canParent = session.is_parent || session.schools.length === 0

  return (
    <div className="min-h-svh lg:grid lg:grid-cols-[17.5rem_1fr]">
      <aside className="border-b border-black/10 bg-fanabe-ink text-fanabe-paper lg:flex lg:min-h-svh lg:flex-col lg:border-b-0 lg:border-r">
        <div className="flex items-center justify-between gap-3 px-5 py-4 lg:block">
          <Logo light />
          <p className="mt-3 hidden text-sm leading-snug text-white/70 lg:block">L’école, la famille, connectées.</p>
          <button type="button" className="min-h-11 rounded-xl px-3 text-sm text-white/80 lg:hidden" onClick={signOut}>
            Sortir
          </button>
        </div>
        <div className="hidden px-4 lg:block">
          <div className="rounded-2xl bg-white/10 px-4 py-3">
            <p className="text-xs uppercase tracking-wider text-white/50">Connecté</p>
            <p className="mt-1 font-semibold">
              {session.person.first_name} {session.person.last_name}
            </p>
            {schoolName && view === 'school' ? <p className="mt-1 text-sm text-white/65">{schoolName}</p> : null}
          </div>
        </div>
        <nav className="flex gap-1 overflow-x-auto px-3 py-3 lg:mt-4 lg:flex-1 lg:flex-col lg:overflow-visible" aria-label="Espaces">
          {canSchool ? (
            <button type="button" className={sideLink(view === 'school')} onClick={() => setView('school')}>
              Espace école
            </button>
          ) : null}
          {canParent ? (
            <button type="button" className={sideLink(view === 'parent')} onClick={() => setView('parent')}>
              Espace famille
            </button>
          ) : null}
        </nav>
        <div className="hidden p-4 lg:block">
          <button type="button" className="min-h-11 w-full rounded-xl border border-white/15 text-sm text-white/80" onClick={signOut}>
            Déconnexion
          </button>
        </div>
      </aside>
      {view === 'school' && canSchool ? (
        <SchoolScreen session={session} onSchoolChange={(schoolId) => signedIn({ ...session, schoolId })} />
      ) : (
        <ParentScreen session={session} />
      )}
    </div>
  )
}

function Logo({ light = false }: { light?: boolean }) {
  return (
    <div className="flex items-center gap-2.5">
      <span className={`grid h-9 w-9 place-items-center rounded-xl ${light ? 'bg-fanabe-leaf' : 'bg-fanabe-leaf text-white'}`}>
        <svg viewBox="0 0 24 24" className="h-5 w-5 fill-fanabe-paper" aria-hidden>
          <path d="M12 3c.5 3.4 2.8 5.8 6.5 6.6C18.2 16.2 14.4 19.5 12 20.8 9.6 19.5 5.8 16.2 5.5 9.6 9.2 8.8 11.5 6.4 12 3z" />
        </svg>
      </span>
      <span className={`font-display text-xl font-semibold tracking-tight ${light ? 'text-white' : 'text-fanabe-ink'}`}>
        FANABE
      </span>
    </div>
  )
}

function sideLink(active: boolean): string {
  return `min-h-11 shrink-0 rounded-xl px-4 text-left text-sm font-medium ${
    active ? 'bg-fanabe-leaf text-white' : 'text-white/75 hover:bg-white/10'
  }`
}

function formatAr(amount: number): string {
  return `${new Intl.NumberFormat('fr-FR').format(amount)} Ar`
}

function formatDate(value: string): string {
  return new Date(`${value}T00:00:00`).toLocaleDateString('fr-FR', {
    day: 'numeric',
    month: 'short',
    year: 'numeric',
  })
}

function initials(first?: string, last?: string): string {
  return `${first?.[0] ?? ''}${last?.[0] ?? ''}`.toUpperCase() || '?'
}

function kindLabel(kind?: string): string {
  if (kind === 'student') return 'Élève'
  if (kind === 'parent') return 'Parent'
  if (kind === 'staff') return 'Personnel'
  return kind ?? 'Personne'
}

function invoiceLabel(status?: string): string {
  if (status === 'paid') return 'Soldée'
  if (status === 'partially_paid') return 'Partiel'
  if (status === 'issued') return 'À payer'
  return status ?? ''
}

function Avatar({ first, last }: { first?: string; last?: string }) {
  return (
    <span className="grid h-10 w-10 shrink-0 place-items-center rounded-full bg-fanabe-mist text-sm font-semibold text-fanabe-leaf">
      {initials(first, last)}
    </span>
  )
}

function Card({ children, className = '' }: { children: ReactNode; className?: string }) {
  return <section className={`rounded-2xl bg-fanabe-paper p-5 shadow-sm shadow-black/5 ${className}`}>{children}</section>
}

function Banner({ message, onClear }: { message: string; onClear: () => void }) {
  const error = /impossible|invalide|erreur/i.test(message)
  return (
    <p
      role="status"
      className={`mt-4 flex items-start justify-between gap-3 rounded-2xl px-4 py-3 text-sm ${
        error ? 'bg-red-50 text-red-900' : 'bg-fanabe-mist text-fanabe-leaf-dark'
      }`}
    >
      <span>{message}</span>
      <button type="button" className="min-h-11 min-w-11 text-lg" onClick={onClear} aria-label="Fermer">
        ×
      </button>
    </p>
  )
}

async function copyText(value: string): Promise<void> {
  await navigator.clipboard.writeText(value)
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
    { label: 'Direction Antsahabe', email: 'direction.antsahabe@fanabe.test' },
    { label: 'Parent Andry', email: 'parent.andry@fanabe.test' },
    { label: 'Parent de Fanja', email: 'parent.d@fanabe.test' },
  ]

  return (
    <main className="mx-auto grid min-h-svh max-w-6xl lg:grid-cols-[1.1fr_0.9fr]">
      <section className="hidden flex-col justify-between bg-fanabe-ink px-12 py-12 text-fanabe-paper lg:flex">
        <Logo light />
        <div>
          <p className="font-display text-4xl leading-tight">L’école, la famille, connectées.</p>
          <p className="mt-4 max-w-md text-base leading-relaxed text-white/75">
            Inscrire une famille, suivre la classe, noter la présence, enregistrer un paiement déjà reçu, et laisser
            le parent voir son solde — sans SMS, sans encaissement en ligne.
          </p>
        </div>
        <p className="text-sm text-white/50">FANABE · Madagascar · démo 2026-2027</p>
      </section>
      <section className="flex flex-col justify-center px-6 py-12 sm:px-10">
        <div className="lg:hidden">
          <Logo />
        </div>
        <h1 className="mt-8 font-display text-3xl font-semibold tracking-tight lg:mt-0">Bienvenue</h1>
        <p className="mt-2 text-neutral-700">Connectez-vous avec le compte de l’école ou le code imprimé du parent.</p>
        <div className="mt-6 grid grid-cols-2 gap-2 rounded-2xl bg-black/5 p-1">
          <button type="button" className={modeTab(mode === 'login')} onClick={() => setMode('login')}>
            Connexion
          </button>
          <button type="button" className={modeTab(mode === 'invite')} onClick={() => setMode('invite')}>
            Code d’invitation
          </button>
        </div>
        <form onSubmit={onSubmit} className="mt-6 space-y-4" aria-label="Connexion">
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
          <button type="submit" disabled={busy} className={primaryButtonClass}>
            {busy ? 'Connexion…' : mode === 'login' ? 'Entrer dans FANABE' : 'Activer mon compte'}
          </button>
        </form>
        {message ? <Banner message={message} onClear={() => setMessage(null)} /> : null}
        <div className="mt-8">
          <p className="text-xs font-semibold uppercase tracking-wider text-neutral-500">Comptes de démonstration</p>
          <div className="mt-2 flex flex-wrap gap-2">
            {demos.map((demo) => (
              <button
                key={demo.email}
                type="button"
                className="min-h-11 rounded-full bg-fanabe-paper px-3 text-sm shadow-sm"
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
          <p className="mt-2 text-xs text-neutral-500">Mot de passe : password</p>
        </div>
      </section>
    </main>
  )
}

function modeTab(active: boolean): string {
  return `min-h-11 rounded-xl text-sm font-semibold ${active ? 'bg-fanabe-paper text-fanabe-ink shadow-sm' : 'text-neutral-600'}`
}

function Field({ label, children }: { label: string; children: ReactNode }) {
  return (
    <label className="block text-sm font-medium">
      {label}
      {children}
    </label>
  )
}

const inputClass =
  'mt-1 w-full rounded-xl border border-black/10 bg-white px-3 py-2.5 text-base outline-none ring-fanabe-leaf focus:ring-2'
const primaryButtonClass =
  'min-h-11 w-full rounded-xl bg-fanabe-leaf px-4 py-2.5 text-base font-semibold text-white hover:bg-fanabe-leaf-dark disabled:opacity-60'
const secondaryButtonClass =
  'min-h-11 rounded-xl border border-black/10 bg-white px-4 py-2 text-sm font-semibold disabled:opacity-60'

function SchoolScreen({
  session,
  onSchoolChange,
}: {
  session: Session
  onSchoolChange: (schoolId: string) => void
}) {
  const schoolId = session.schoolId ?? session.schools[0].id
  const [tab, setTab] = useState<SchoolTab>('accueil')
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
  const [form, setForm] = useState({
    parent_first: 'Voahangy',
    parent_last: 'Rasoa',
    parent_phone: '0349876543',
    parent_email: '',
    student_first: 'Tiana',
    student_last: 'Rasoa',
    student_birth: '2014-06-20',
  })
  const [newClassName, setNewClassName] = useState('6ème B')
  const [newClassGrade, setNewClassGrade] = useState('')
  const [selectedClassroom, setSelectedClassroom] = useState('')
  const [attendanceDate, setAttendanceDate] = useState(() => new Date().toISOString().slice(0, 10))
  const [marks, setMarks] = useState<Record<string, string>>({})
  const [selectedEnrollment, setSelectedEnrollment] = useState('')
  const [invoice, setInvoice] = useState<InvoiceRow | null>(null)
  const [paymentAmount, setPaymentAmount] = useState('50000')
  const [paymentMethod, setPaymentMethod] = useState('cash')
  const [receipt, setReceipt] = useState<string | null>(null)

  const auth = useMemo(() => ({ token: session.token }), [session.token])
  const schoolName = session.schools.find((row) => row.id === schoolId)?.name ?? 'Établissement'
  const activeEnrollments = enrollments.filter((row) => row.status === 'active')
  const unassigned = activeEnrollments.filter((row) => !row.classroom_id)
  const outstanding = activeEnrollments.reduce((sum, row) => sum + (row.invoice?.remaining_amount ?? 0), 0)
  const roster = activeEnrollments.filter((row) => row.classroom_id === selectedClassroom)
  const filteredPeople = people.filter((person) => {
    const hay = `${person.first_name} ${person.last_name} ${person.public_id}`.toLowerCase()
    return hay.includes(query.toLowerCase())
  })

  async function refresh() {
    const years = await api<{ data: Array<{ id: string; is_current: boolean; label: string }> }>(
      `/api/v1/schools/${schoolId}/years`,
      auth,
    )
    const current = years.data.find((year) => year.is_current) ?? years.data[0]
    setYearId(current?.id ?? '')
    setYearLabel(current?.label ?? '2026-2027')
    const [list, classList, gradeList, enrollmentList] = await Promise.all([
      api<{ data: PersonRow[] }>(`/api/v1/schools/${schoolId}/people`, auth),
      api<{ data: ClassroomRow[] }>(`/api/v1/schools/${schoolId}/classrooms`, auth),
      api<{ data: Array<{ id: string; name: string }> }>(`/api/v1/schools/${schoolId}/grade-levels`, auth),
      api<{ data: EnrollmentRow[] }>(`/api/v1/schools/${schoolId}/enrollments`, auth),
    ])
    setPeople(list.data)
    setClassrooms(classList.data)
    setGrades(gradeList.data)
    setEnrollments(enrollmentList.data)
    setSelectedClassroom((prev) => prev || classList.data[0]?.id || '')
    setNewClassGrade((prev) => prev || gradeList.data[0]?.id || '')
    const active = enrollmentList.data.find((row) => row.status === 'active')
    setSelectedEnrollment((prev) => prev || active?.id || '')
  }

  useEffect(() => {
    refresh().catch((error: Error) => setMessage(error.message))
  }, [schoolId, session.token])

  async function createFamily(event: FormEvent) {
    event.preventDefault()
    setBusy(true)
    setMessage(null)
    setInvitation(null)
    try {
      const created = await api<{ invitation_code: string }>(`/api/v1/schools/${schoolId}/families`, {
        ...auth,
        method: 'POST',
        body: JSON.stringify({
          school_year_id: yearId,
          parent: {
            first_name: form.parent_first,
            last_name: form.parent_last,
            phone: form.parent_phone,
            email: form.parent_email || undefined,
          },
          student: {
            first_name: form.student_first,
            last_name: form.student_last,
            birth_date: form.student_birth,
          },
        }),
      })
      setInvitation(created.invitation_code)
      setMessage('Famille inscrite. Remettez le code d’invitation au parent.')
      await refresh()
    } catch (error) {
      setMessage(error instanceof Error ? error.message : 'Inscription impossible.')
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
      await refresh()
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
      await refresh()
    } catch (error) {
      setMessage(error instanceof Error ? error.message : 'Affectation impossible.')
    } finally {
      setBusy(false)
    }
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
    if (!selectedClassroom || tab !== 'presence') return
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
          records: roster.map((row) => ({
            enrollment_id: row.id,
            status: marks[row.id] ?? 'present',
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
      await refresh()
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
      await refresh()
    } catch (error) {
      setMessage(error instanceof Error ? error.message : 'Paiement impossible à enregistrer.')
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

  const selectedStudent = activeEnrollments.find((row) => row.id === selectedEnrollment)

  return (
    <main className="min-w-0 px-4 py-6 sm:px-8">
      <header className="flex flex-wrap items-end justify-between gap-4">
        <div>
          <p className="text-xs font-semibold uppercase tracking-[0.18em] text-fanabe-leaf">Espace école</p>
          <h1 className="font-display text-3xl font-semibold tracking-tight">{schoolName}</h1>
          <p className="mt-1 text-sm text-neutral-600">Année scolaire {yearLabel}</p>
        </div>
        {session.schools.length > 1 ? (
          <label className="block min-w-56 text-sm font-medium">
            Établissement
            <select className={inputClass} value={schoolId} onChange={(event) => onSchoolChange(event.target.value)}>
              {session.schools.map((school) => (
                <option key={school.id} value={school.id}>
                  {school.name}
                </option>
              ))}
            </select>
          </label>
        ) : null}
      </header>

      <nav className="mt-6 flex gap-1 overflow-x-auto rounded-2xl bg-black/5 p-1" aria-label="Sections école">
        {SCHOOL_NAV.map((item) => (
          <button key={item.id} type="button" className={schoolTab(tab === item.id)} onClick={() => setTab(item.id)}>
            <span className="block">{item.label}</span>
            <span className="hidden text-[11px] font-normal opacity-70 sm:block">{item.hint}</span>
          </button>
        ))}
      </nav>
      {message ? <Banner message={message} onClear={() => setMessage(null)} /> : null}

      {tab === 'accueil' ? (
        <div className="mt-6 space-y-4">
          <div className="grid gap-3 sm:grid-cols-2 xl:grid-cols-4">
            <Stat label="Élèves inscrits" value={String(activeEnrollments.length)} hint="Inscriptions actives" />
            <Stat label="Sans classe" value={String(unassigned.length)} hint="À affecter" />
            <Stat label="Classes" value={String(classrooms.length)} hint={yearLabel} />
            <Stat label="Reste à encaisser" value={formatAr(outstanding)} hint="Soldes ouverts" />
          </div>
          <div className="grid gap-3 sm:grid-cols-3">
            <QuickAction title="Faire l’appel" body="Noter la présence d’une classe." onClick={() => setTab('presence')} />
            <QuickAction title="Enregistrer un paiement" body="Un reçu est émis tout de suite." onClick={() => setTab('caisse')} />
            <QuickAction title="Inscrire une famille" body="Parent + élève + code imprimé." onClick={() => setTab('famille')} />
          </div>
          <Card>
            <h2 className="text-lg font-semibold">À traiter</h2>
            <ul className="mt-3 divide-y divide-black/5">
              {unassigned.slice(0, 5).map((row) => (
                <li key={row.id} className="flex items-center justify-between gap-3 py-3">
                  <span>
                    {row.person?.first_name} {row.person?.last_name}
                  </span>
                  <button type="button" className="text-sm font-semibold text-fanabe-leaf" onClick={() => setTab('classe')}>
                    Affecter
                  </button>
                </li>
              ))}
              {unassigned.length === 0 ? <li className="py-3 text-sm text-neutral-600">Tous les élèves ont une classe.</li> : null}
            </ul>
          </Card>
        </div>
      ) : null}

      {tab === 'famille' ? (
        <div className="mt-6 grid gap-4 lg:grid-cols-[1.05fr_0.95fr]">
          <Card>
            <h2 className="text-lg font-semibold">Nouvelle famille</h2>
            <p className="mt-1 text-sm text-neutral-600">Le parent recevra un code à imprimer pour activer son compte — pas de SMS.</p>
            <form onSubmit={createFamily} className="mt-4 space-y-3">
              <p className="text-xs font-semibold uppercase tracking-wider text-neutral-500">Parent</p>
              <div className="grid grid-cols-2 gap-3">
                <input className={inputClass} value={form.parent_first} onChange={(e) => setForm({ ...form, parent_first: e.target.value })} placeholder="Prénom" required />
                <input className={inputClass} value={form.parent_last} onChange={(e) => setForm({ ...form, parent_last: e.target.value })} placeholder="Nom" required />
              </div>
              <input className={inputClass} value={form.parent_phone} onChange={(e) => setForm({ ...form, parent_phone: e.target.value })} placeholder="Téléphone" />
              <input className={inputClass} type="email" value={form.parent_email} onChange={(e) => setForm({ ...form, parent_email: e.target.value })} placeholder="Email (facultatif)" />
              <p className="pt-2 text-xs font-semibold uppercase tracking-wider text-neutral-500">Élève</p>
              <div className="grid grid-cols-2 gap-3">
                <input className={inputClass} value={form.student_first} onChange={(e) => setForm({ ...form, student_first: e.target.value })} placeholder="Prénom" required />
                <input className={inputClass} value={form.student_last} onChange={(e) => setForm({ ...form, student_last: e.target.value })} placeholder="Nom" required />
              </div>
              <Field label="Date de naissance">
                <input className={inputClass} type="date" value={form.student_birth} onChange={(e) => setForm({ ...form, student_birth: e.target.value })} required />
              </Field>
              <button type="submit" disabled={busy || !yearId} className={primaryButtonClass}>
                {busy ? 'Enregistrement…' : 'Inscrire la famille'}
              </button>
            </form>
            {invitation ? (
              <div className="mt-4 rounded-2xl bg-fanabe-mist px-4 py-3 text-sm">
                <p>Code d’invitation à remettre au parent</p>
                <div className="mt-2 flex items-center justify-between gap-3">
                  <strong className="font-mono text-lg tracking-wide">{invitation}</strong>
                  <button type="button" className={secondaryButtonClass} onClick={() => copyText(invitation)}>
                    Copier
                  </button>
                </div>
              </div>
            ) : null}
          </Card>
          <Card>
            <div className="flex items-center justify-between gap-3">
              <h2 className="text-lg font-semibold">Personnes liées</h2>
              <span className="text-sm text-neutral-500">{people.length}</span>
            </div>
            <input className={inputClass} value={query} onChange={(e) => setQuery(e.target.value)} placeholder="Rechercher un nom ou un identifiant" />
            <ul className="mt-4 max-h-[32rem] space-y-2 overflow-auto">
              {filteredPeople.map((person) => (
                <li key={`${person.id}-${person.kind ?? ''}`} className="flex items-center gap-3 rounded-xl bg-fanabe-sand/70 px-3 py-3">
                  <Avatar first={person.first_name} last={person.last_name} />
                  <div className="min-w-0">
                    <p className="truncate font-medium">
                      {person.first_name} {person.last_name}
                    </p>
                    <p className="truncate text-sm text-neutral-600">
                      {kindLabel(person.kind)} · {person.public_id}
                    </p>
                  </div>
                </li>
              ))}
            </ul>
          </Card>
        </div>
      ) : null}

      {tab === 'classe' ? (
        <div className="mt-6 grid gap-4 lg:grid-cols-2">
          <Card>
            <h2 className="text-lg font-semibold">Classes de l’année</h2>
            <form onSubmit={createClassroom} className="mt-4 space-y-3">
              <Field label="Niveau">
                <select className={inputClass} value={newClassGrade} onChange={(e) => setNewClassGrade(e.target.value)}>
                  {grades.map((grade) => (
                    <option key={grade.id} value={grade.id}>
                      {grade.name}
                    </option>
                  ))}
                </select>
              </Field>
              <Field label="Nom de la classe">
                <input className={inputClass} value={newClassName} onChange={(e) => setNewClassName(e.target.value)} required />
              </Field>
              <button type="submit" disabled={busy || !yearId || !newClassGrade} className={primaryButtonClass}>
                Créer la classe
              </button>
            </form>
            <ul className="mt-5 space-y-2">
              {classrooms.map((classroom) => (
                <li key={classroom.id} className="flex items-center justify-between rounded-xl bg-fanabe-sand/70 px-4 py-3">
                  <div>
                    <p className="font-medium">{classroom.name}</p>
                    <p className="text-sm text-neutral-600">{classroom.grade_level?.name}</p>
                  </div>
                  <span className="text-sm text-neutral-500">
                    {activeEnrollments.filter((row) => row.classroom_id === classroom.id).length} élève(s)
                  </span>
                </li>
              ))}
            </ul>
          </Card>
          <Card>
            <h2 className="text-lg font-semibold">Affecter les élèves</h2>
            <ul className="mt-4 space-y-2">
              {activeEnrollments.map((row) => (
                <li key={row.id} className="rounded-xl bg-fanabe-sand/70 px-4 py-3">
                  <div className="flex items-center gap-3">
                    <Avatar first={row.person?.first_name} last={row.person?.last_name} />
                    <div>
                      <p className="font-medium">
                        {row.person?.first_name} {row.person?.last_name}
                      </p>
                      <p className="text-sm text-neutral-600">{row.classroom?.name ?? 'Pas encore de classe'}</p>
                    </div>
                  </div>
                  <select
                    className={inputClass}
                    value={row.classroom_id ?? ''}
                    onChange={(event) => {
                      if (event.target.value) void assignClassroom(row.id, event.target.value)
                    }}
                  >
                    <option value="">Choisir une classe</option>
                    {classrooms.map((classroom) => (
                      <option key={classroom.id} value={classroom.id}>
                        {classroom.name}
                      </option>
                    ))}
                  </select>
                </li>
              ))}
            </ul>
          </Card>
        </div>
      ) : null}

      {tab === 'presence' ? (
        <Card className="mt-6">
          <h2 className="text-lg font-semibold">Appel du jour</h2>
          <p className="mt-1 text-sm text-neutral-600">Une seule écriture possible hors ligne : la présence. Le reste attend le réseau.</p>
          <form onSubmit={saveAttendance} className="mt-4 space-y-4">
            <div className="grid gap-3 sm:grid-cols-2">
              <Field label="Classe">
                <select className={inputClass} value={selectedClassroom} onChange={(e) => setSelectedClassroom(e.target.value)}>
                  {classrooms.map((classroom) => (
                    <option key={classroom.id} value={classroom.id}>
                      {classroom.name}
                    </option>
                  ))}
                </select>
              </Field>
              <Field label="Date">
                <input className={inputClass} type="date" value={attendanceDate} onChange={(e) => setAttendanceDate(e.target.value)} />
              </Field>
            </div>
            {roster.length === 0 ? (
              <p className="rounded-xl bg-fanabe-sand/80 px-4 py-6 text-sm text-neutral-600">Aucun élève dans cette classe pour le moment.</p>
            ) : (
              <ul className="space-y-2">
                {roster.map((row) => (
                  <li key={row.id} className="flex flex-col gap-3 rounded-xl bg-fanabe-sand/70 px-4 py-3 sm:flex-row sm:items-center sm:justify-between">
                    <div className="flex items-center gap-3">
                      <Avatar first={row.person?.first_name} last={row.person?.last_name} />
                      <p className="font-medium">
                        {row.person?.first_name} {row.person?.last_name}
                      </p>
                    </div>
                    <AttendancePills value={marks[row.id] ?? 'present'} onChange={(status) => setMarks({ ...marks, [row.id]: status })} />
                  </li>
                ))}
              </ul>
            )}
            <button type="submit" disabled={busy || roster.length === 0} className={primaryButtonClass}>
              Enregistrer l’appel
            </button>
          </form>
        </Card>
      ) : null}

      {tab === 'caisse' ? (
        <div className="mt-6 grid gap-4 lg:grid-cols-[0.9fr_1.1fr]">
          <Card>
            <div className="flex items-center justify-between gap-2">
              <h2 className="text-lg font-semibold">Élèves</h2>
              <button type="button" className="text-sm font-semibold text-fanabe-leaf" onClick={() => downloadCsv().catch((error: Error) => setMessage(error.message))}>
                Export CSV
              </button>
            </div>
            <ul className="mt-4 max-h-[36rem] space-y-2 overflow-auto">
              {activeEnrollments.map((row) => {
                const selected = row.id === selectedEnrollment
                return (
                  <li key={row.id}>
                    <button
                      type="button"
                      onClick={() => setSelectedEnrollment(row.id)}
                      className={`flex w-full items-center gap-3 rounded-xl px-3 py-3 text-left ${
                        selected ? 'bg-fanabe-mist ring-2 ring-fanabe-leaf' : 'bg-fanabe-sand/70'
                      }`}
                    >
                      <Avatar first={row.person?.first_name} last={row.person?.last_name} />
                      <span className="min-w-0 flex-1">
                        <span className="block truncate font-medium">
                          {row.person?.first_name} {row.person?.last_name}
                        </span>
                        <span className="block text-sm text-neutral-600">{row.classroom?.name ?? 'Sans classe'}</span>
                      </span>
                      <span className="text-sm font-semibold">
                        {row.invoice ? formatAr(row.invoice.remaining_amount) : '—'}
                      </span>
                    </button>
                  </li>
                )
              })}
            </ul>
          </Card>
          <Card>
            <h2 className="text-lg font-semibold">
              {selectedStudent ? `${selectedStudent.person?.first_name} ${selectedStudent.person?.last_name}` : 'Caisse'}
            </h2>
            <p className="mt-1 text-sm text-neutral-600">
              FANABE note un paiement déjà reçu (espèces, mobile money, virement). Il n’encaisse rien en ligne.
            </p>
            <div className="mt-4 flex flex-wrap gap-2">
              <button type="button" disabled={busy || !selectedEnrollment} className={secondaryButtonClass} onClick={generateInvoice}>
                Générer la facture
              </button>
            </div>
            {invoice ? (
              <div className="mt-4 rounded-2xl bg-fanabe-sand/80 p-4">
                <div className="flex items-center justify-between gap-2">
                  <p className="font-semibold">{invoice.number}</p>
                  <span className="rounded-full bg-white px-3 py-1 text-xs font-semibold">{invoiceLabel(invoice.status)}</span>
                </div>
                <div className="mt-3 h-2 overflow-hidden rounded-full bg-white">
                  <div
                    className="h-full rounded-full bg-fanabe-leaf"
                    style={{ width: `${invoice.net_amount === 0 ? 0 : Math.min(100, (invoice.paid_amount / invoice.net_amount) * 100)}%` }}
                  />
                </div>
                <p className="mt-2 text-sm">
                  Payé {formatAr(invoice.paid_amount)} sur {formatAr(invoice.net_amount)} · reste{' '}
                  <strong>{formatAr(invoice.remaining_amount)}</strong>
                </p>
                <ul className="mt-3 space-y-2 text-sm">
                  {invoice.installments.map((row) => (
                    <li key={row.id} className="flex justify-between gap-3">
                      <span>{formatDate(row.due_on)}</span>
                      <span>{formatAr(row.remaining_amount)} restant</span>
                    </li>
                  ))}
                </ul>
              </div>
            ) : (
              <p className="mt-4 rounded-xl bg-fanabe-sand/80 px-4 py-6 text-sm text-neutral-600">Pas encore de facture pour cet élève.</p>
            )}
            <form onSubmit={recordPayment} className="mt-5 space-y-3">
              <Field label="Montant reçu (Ariary)">
                <input className={inputClass} type="number" min={1} step={1} value={paymentAmount} onChange={(e) => setPaymentAmount(e.target.value)} required />
              </Field>
              <Field label="Mode">
                <select className={inputClass} value={paymentMethod} onChange={(e) => setPaymentMethod(e.target.value)}>
                  <option value="cash">Espèces</option>
                  <option value="mobile_money">Mobile money</option>
                  <option value="bank_transfer">Virement</option>
                </select>
              </Field>
              <button type="submit" disabled={busy || !invoice} className={primaryButtonClass}>
                {busy ? 'Enregistrement…' : 'Enregistrer le paiement'}
              </button>
            </form>
            {receipt ? (
              <div className="mt-4 flex items-center justify-between gap-3 rounded-2xl bg-fanabe-mist px-4 py-3 text-sm">
                <span>
                  Reçu émis : <strong>{receipt}</strong>
                </span>
                <button type="button" className={secondaryButtonClass} onClick={() => copyText(receipt)}>
                  Copier
                </button>
              </div>
            ) : null}
          </Card>
        </div>
      ) : null}
    </main>
  )
}

function schoolTab(active: boolean): string {
  return `min-h-11 min-w-28 flex-1 rounded-xl px-3 py-2 text-sm font-semibold ${
    active ? 'bg-fanabe-paper text-fanabe-ink shadow-sm' : 'text-neutral-600'
  }`
}

function Stat({ label, value, hint }: { label: string; value: string; hint: string }) {
  return (
    <Card>
      <p className="text-xs font-semibold uppercase tracking-wider text-neutral-500">{label}</p>
      <p className="mt-2 font-display text-2xl font-semibold">{value}</p>
      <p className="mt-1 text-sm text-neutral-600">{hint}</p>
    </Card>
  )
}

function QuickAction({ title, body, onClick }: { title: string; body: string; onClick: () => void }) {
  return (
    <button type="button" onClick={onClick} className="rounded-2xl bg-fanabe-paper p-5 text-left shadow-sm shadow-black/5">
      <p className="font-semibold">{title}</p>
      <p className="mt-1 text-sm text-neutral-600">{body}</p>
    </button>
  )
}

function AttendancePills({ value, onChange }: { value: string; onChange: (status: string) => void }) {
  const options = [
    { id: 'present', label: 'Présent' },
    { id: 'absent', label: 'Absent' },
    { id: 'late', label: 'Retard' },
    { id: 'excused', label: 'Excusé' },
  ]
  return (
    <div className="flex flex-wrap gap-1">
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
            className={`min-h-11 rounded-full px-3 text-sm font-semibold ${active ? tone : 'bg-white text-neutral-700'}`}
            onClick={() => onChange(option.id)}
          >
            {option.label}
          </button>
        )
      })}
    </div>
  )
}

function ParentScreen({ session }: { session: Session }) {
  const [children, setChildren] = useState<PersonRow[]>([])
  const [finances, setFinances] = useState<Record<string, ChildFinance>>({})
  const [message, setMessage] = useState<string | null>(null)

  useEffect(() => {
    api<{ data: PersonRow[] }>('/api/v1/parent/children', { token: session.token })
      .then(async (payload) => {
        setChildren(payload.data)
        const entries = await Promise.all(
          payload.data.map(async (child) => {
            const finance = await api<ChildFinance>(`/api/v1/parent/children/${child.id}/finance`, {
              token: session.token,
            })
            return [child.id, finance] as const
          }),
        )
        setFinances(Object.fromEntries(entries))
      })
      .catch((error: Error) => setMessage(error.message))
  }, [session.token])

  const totalRemaining = Object.values(finances).reduce((sum, row) => sum + row.remaining_amount, 0)

  return (
    <main className="min-w-0 px-4 py-6 sm:px-8">
      <p className="text-xs font-semibold uppercase tracking-[0.18em] text-fanabe-leaf">Espace famille</p>
      <h1 className="font-display text-3xl font-semibold tracking-tight">
        Bonjour {session.person.first_name}
      </h1>
      <p className="mt-1 text-sm text-neutral-600">Identifiant FANABE {session.person.public_id}</p>
      {message ? <Banner message={message} onClear={() => setMessage(null)} /> : null}

      <div className="mt-6 grid gap-3 sm:grid-cols-2">
        <Stat label="Enfants" value={String(children.length)} hint="Rattachés à ce compte" />
        <Stat
          label="Reste à payer"
          value={formatAr(totalRemaining)}
          hint={totalRemaining === 0 ? 'Rien n’est dû pour le moment' : 'Toutes écoles confondues'}
        />
      </div>

      <ul className="mt-6 space-y-4">
        {children.map((child) => {
          const finance = finances[child.id]
          return (
            <li key={child.id}>
              <Card>
                <div className="flex items-start gap-3">
                  <Avatar first={child.first_name} last={child.last_name} />
                  <div className="min-w-0 flex-1">
                    <p className="text-lg font-semibold">
                      {child.first_name} {child.last_name}
                    </p>
                    <p className="text-sm text-neutral-600">{child.public_id}</p>
                  </div>
                  {finance ? (
                    <p className={`text-right font-display text-xl font-semibold ${finance.remaining_amount > 0 ? 'text-fanabe-clay' : 'text-fanabe-leaf'}`}>
                      {formatAr(finance.remaining_amount)}
                    </p>
                  ) : null}
                </div>
                {finance?.data.map((row, index) => (
                  <div key={index} className="mt-4 rounded-xl bg-fanabe-sand/80 p-4 text-sm">
                    <p className="font-medium">
                      {row.school?.name}
                      {row.classroom ? ` · ${row.classroom.name}` : ''}
                    </p>
                    {row.invoice ? (
                      <>
                        <p className="mt-1">
                          Facture {row.invoice.number} · {invoiceLabel(row.invoice.status)}
                        </p>
                        <div className="mt-2 h-2 overflow-hidden rounded-full bg-white">
                          <div
                            className="h-full rounded-full bg-fanabe-leaf"
                            style={{
                              width: `${row.invoice.net_amount === 0 ? 0 : Math.min(100, (row.invoice.paid_amount / row.invoice.net_amount) * 100)}%`,
                            }}
                          />
                        </div>
                        <ul className="mt-3 space-y-1 text-neutral-700">
                          {row.invoice.installments.map((item) => (
                            <li key={item.id} className="flex justify-between gap-3">
                              <span>{formatDate(item.due_on)}</span>
                              <span>{formatAr(item.remaining_amount)}</span>
                            </li>
                          ))}
                        </ul>
                      </>
                    ) : (
                      <p className="mt-1 text-neutral-600">L’école n’a pas encore émis de facture.</p>
                    )}
                    {row.payments.length > 0 ? (
                      <ul className="mt-3 border-t border-black/5 pt-3 text-neutral-600">
                        {row.payments.map((payment) => (
                          <li key={payment.receipt_number ?? payment.received_on} className="flex justify-between gap-3">
                            <span>Reçu {payment.receipt_number}</span>
                            <span>{formatAr(payment.amount)}</span>
                          </li>
                        ))}
                      </ul>
                    ) : null}
                  </div>
                ))}
              </Card>
            </li>
          )
        })}
      </ul>
      {children.length === 0 && !message ? (
        <p className="mt-6 rounded-2xl bg-fanabe-paper px-4 py-8 text-center text-sm text-neutral-600">
          Aucun enfant n’est encore rattaché à ce compte. L’école peut vous remettre un code d’invitation.
        </p>
      ) : null}
    </main>
  )
}
