import './bootstrap';

import Alpine from 'alpinejs';
import './installation-act-photo-gallery';
import { bindThemeToggles } from './theme-toggle';
import { layoutTokenEditorMixin } from './layout-template-token-editor';
import { registerLayoutApplicationCreate } from './layout-application-create';

window.Alpine = Alpine;
window.layoutTokenEditorMixin = layoutTokenEditorMixin;

registerLayoutApplicationCreate(Alpine);

Alpine.start();
bindThemeToggles();
