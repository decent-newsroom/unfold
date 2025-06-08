// assets/controllers/progress_bar_controller.js
import { Controller } from "@hotwired/stimulus";

export default class extends Controller {
  static targets = ["bar"];

  connect() {
    // Listen for clicks on the entire document instead of just the controller element
    document.addEventListener("click", this.handleInteraction.bind(this));
    document.addEventListener("touchend", this.handleInteraction.bind(this));
  }

  disconnect() {
    // Clean up event listener when controller disconnects
    document.removeEventListener("click", this.handleInteraction.bind(this));
    document.removeEventListener("touchend", this.handleInteraction.bind(this));
  }

  handleInteraction(event) {
    const link = event.target.closest("a");
    if (link && !link.hasAttribute("data-no-progress") &&
        !event.ctrlKey && !event.metaKey && !event.shiftKey) {
      this.start();
    }
  }

  start() {
    this.barTarget.style.width = "0";
    this.barTarget.style.transition = "none";
    setTimeout(() => {
      this.barTarget.style.transition = "width 5s ease-in-out";
      this.barTarget.style.width = "100%";
    }, 10);
  }
}
