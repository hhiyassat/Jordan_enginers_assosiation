import React from 'react'
import ReactDOM from 'react-dom/client'
import App from './App'
import './index.css'
// Import the i18n bootstrap for its side effects — it registers the two
// locales, wires <html dir>/lang synchronisation, and reads the
// persisted language from localStorage before React ever mounts.
import './i18n'

ReactDOM.createRoot(document.getElementById('root')!).render(
  <React.StrictMode>
    <App />
  </React.StrictMode>,
)
