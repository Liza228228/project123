import './bootstrap';

import Alpine from 'alpinejs';
import { bindThemeToggles } from './theme-toggle';

window.Alpine = Alpine;

Alpine.start();
bindThemeToggles();
