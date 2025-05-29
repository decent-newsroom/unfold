import { Controller } from '@hotwired/stimulus';

export default class extends Controller {
    static values = {
        identifier: String,
        type: String,
        decoded: String,
        fullMatch: String
    }

    static targets = ['container']

    async connect() {
      await this.fetchPreview();
    }

    async fetchPreview() {
        try {
            // Show loading indicator
            this.containerTarget.innerHTML = '<div class="text-center my-2"><div class="spinner-border spinner-border-sm text-secondary" role="status"></div> Loading preview...</div>';
            console.log(this.decodedValue);
            const data = {
              identifier: this.identifierValue,
              type: this.typeValue,
              decoded: this.decodedValue
            };
            fetch("/preview/", {
              method: "POST",
              headers: {
                "Content-Type": "application/json"
              },
              body: JSON.stringify(data)
            })
              .then(res => {
                if (!res.ok) {
                  throw new Error(`HTTP error! status: ${res.status}`);
                }
                return res.text();
              })
              .then(data => {
                console.log(data);
                this.containerTarget.innerHTML = data;
              })
              .catch(error => {
                console.error("Error:", error);
              });
        } catch (error) {
            console.error('Error fetching Nostr preview:', error);
            this.containerTarget.innerHTML = `<div class="alert alert-warning">Unable to load preview for ${this.fullMatchValue}</div>`;
        }
    }
}
