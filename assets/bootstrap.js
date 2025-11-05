import { startStimulusApp } from '@symfony/stimulus-bundle';
import * as Turbo from '@hotwired/turbo';

// Disable Turbo globally
Turbo.session.drive = false;

const app = startStimulusApp();
