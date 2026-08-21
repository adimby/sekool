import { FormEvent, useState } from 'react'

export default function App() {
  const [email, setEmail] = useState('direction.antsahabe@fanabe.test')
  const [password, setPassword] = useState('password')
  const [message, setMessage] = useState<string | null>(null)
  const [busy, setBusy] = useState(false)

  async function onSubmit(event: FormEvent) {
    event.preventDefault()
    setBusy(true)
    setMessage(null)
    try {
      const response = await fetch('/api/v1/auth/login', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json', Accept: 'application/json' },
        body: JSON.stringify({ email, password }),
      })
      const payload = await response.json()
      if (!response.ok) {
        setMessage(payload.message ?? 'Connexion impossible.')
        return
      }
      sessionStorage.setItem('fanabe.token', payload.token)
      setMessage('Connexion réussie. Le cockpit arrive en phase 2.')
    } catch {
      setMessage('Le serveur API est injoignable. Lancez `php artisan serve` dans /api.')
    } finally {
      setBusy(false)
    }
  }

  return (
    <main className="mx-auto flex min-h-svh max-w-md flex-col justify-center px-6 py-12">
      <p className="text-sm font-semibold tracking-[0.2em] text-fanabe-leaf uppercase">FANABE</p>
      <h1 className="mt-2 text-3xl font-semibold tracking-tight">L&apos;école, la famille, connectées.</h1>
      <p className="mt-3 text-base leading-relaxed text-neutral-700">
        Connexion du personnel. Les familles recevront un code d&apos;invitation — jamais de SMS.
      </p>

      <form onSubmit={onSubmit} className="mt-8 space-y-4" aria-label="Connexion">
        <label className="block text-sm font-medium">
          Email
          <input
            className="mt-1 w-full rounded-lg border border-neutral-300 bg-white px-3 py-2.5 text-base outline-none ring-fanabe-leaf focus:ring-2"
            type="email"
            autoComplete="username"
            value={email}
            onChange={(e) => setEmail(e.target.value)}
            required
          />
        </label>
        <label className="block text-sm font-medium">
          Mot de passe
          <input
            className="mt-1 w-full rounded-lg border border-neutral-300 bg-white px-3 py-2.5 text-base outline-none ring-fanabe-leaf focus:ring-2"
            type="password"
            autoComplete="current-password"
            value={password}
            onChange={(e) => setPassword(e.target.value)}
            required
          />
        </label>
        <button
          type="submit"
          disabled={busy}
          className="min-h-11 w-full rounded-lg bg-fanabe-leaf px-4 py-2.5 text-base font-semibold text-white disabled:opacity-60"
        >
          {busy ? 'Connexion…' : 'Se connecter'}
        </button>
      </form>

      {message ? (
        <p className="mt-4 text-sm" role="status">
          {message}
        </p>
      ) : null}
    </main>
  )
}
