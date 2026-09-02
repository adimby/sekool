import { StrictMode } from 'react'
import { createRoot } from 'react-dom/client'
import './index.css'
import App from './App.tsx'

createRoot(document.getElementById('root')!).render(
  <StrictMode>
    <App />
  </StrictMode>,
)

if (import.meta.env.PROD && 'serviceWorker' in navigator) {
  const worker = `${import.meta.env.BASE_URL}sw.js`
  navigator.serviceWorker.register(worker, { scope: import.meta.env.BASE_URL }).catch(() => {
    /* hors ligne au premier chargement : la coquille se cale au prochain passage en ligne */
  })
}
