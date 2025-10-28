import { startStimulusApp } from '@symfony/stimulus-bundle';
import * as Turbo from '@hotwired/turbo';
import UserListFilterController from './controllers/user_list_filter_controller.js';

// Disable Turbo globally
Turbo.session.drive = false;

const app = startStimulusApp();
// register any custom, 3rd party controllers here
app.register('user-list-filter', UserListFilterController);
