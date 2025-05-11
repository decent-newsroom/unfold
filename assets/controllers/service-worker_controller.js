import { Controller } from '@hotwired/stimulus'

export default class extends Controller {
  connect() {
    if ('serviceWorker' in navigator) {
      navigator.serviceWorker.register('/service-worker.js')
        .then(reg => console.log('SW registered:', reg))
        .catch(err => console.error('SW failed:', err));
    }
  }
}
