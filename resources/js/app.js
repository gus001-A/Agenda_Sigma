import '../css/app.css';
import 'primeicons/primeicons.css';
import './bootstrap';

import { createInertiaApp } from '@inertiajs/vue3';
import { resolvePageComponent } from 'laravel-vite-plugin/inertia-helpers';
import { createApp, h } from 'vue';
import { ZiggyVue } from '../../vendor/tightenco/ziggy';

import PrimeVue from 'primevue/config';
import { definePreset } from '@primevue/themes';
import Aura from '@primevue/themes/aura';
import ToastService from 'primevue/toastservice';
import ConfirmationService from 'primevue/confirmationservice';
import Tooltip from 'primevue/tooltip';

import Button from 'primevue/button';
import Dialog from 'primevue/dialog';
import Drawer from 'primevue/drawer';
import Select from 'primevue/select';
import InputText from 'primevue/inputtext';
import InputNumber from 'primevue/inputnumber';
import Textarea from 'primevue/textarea';
import Checkbox from 'primevue/checkbox';
import ToggleSwitch from 'primevue/toggleswitch';
import SelectButton from 'primevue/selectbutton';
import DatePicker from 'primevue/datepicker';
import DataTable from 'primevue/datatable';
import Column from 'primevue/column';
import Tag from 'primevue/tag';
import RadioButton from 'primevue/radiobutton';
import Password from 'primevue/password';
import Paginator from 'primevue/paginator';
import Tabs from 'primevue/tabs';
import TabList from 'primevue/tablist';
import Tab from 'primevue/tab';
import Message from 'primevue/message';
import IconField from 'primevue/iconfield';
import InputIcon from 'primevue/inputicon';

// Paleta del prototipo: verde azulado institucional (#0D5C6B) y ámbar de acento.
const Sigma24 = definePreset(Aura, {
    semantic: {
        primary: {
            50: '#ecf7f9', 100: '#d6eff2', 200: '#b0e0e7', 300: '#7fcbd7', 400: '#45b3c5',
            500: '#0d5c6b', 600: '#09495a', 700: '#083c4a', 800: '#072f3a', 900: '#05242c', 950: '#031418',
        },
        colorScheme: {
            light: { primary: { color: '{primary.500}', hoverColor: '{primary.600}', activeColor: '{primary.700}' } },
            dark: { primary: { color: '{primary.400}', contrastColor: '#06252c', hoverColor: '{primary.300}', activeColor: '{primary.200}' } },
        },
    },
});

const localeEs = {
    accept: 'Sí', reject: 'No', choose: 'Elegir', upload: 'Subir', cancel: 'Cancelar', clear: 'Limpiar', apply: 'Aplicar',
    dayNames: ['Domingo', 'Lunes', 'Martes', 'Miércoles', 'Jueves', 'Viernes', 'Sábado'],
    dayNamesShort: ['Dom', 'Lun', 'Mar', 'Mié', 'Jue', 'Vie', 'Sáb'],
    dayNamesMin: ['D', 'L', 'M', 'X', 'J', 'V', 'S'],
    monthNames: ['Enero', 'Febrero', 'Marzo', 'Abril', 'Mayo', 'Junio', 'Julio', 'Agosto', 'Septiembre', 'Octubre', 'Noviembre', 'Diciembre'],
    monthNamesShort: ['Ene', 'Feb', 'Mar', 'Abr', 'May', 'Jun', 'Jul', 'Ago', 'Sep', 'Oct', 'Nov', 'Dic'],
    today: 'Hoy', weekHeader: 'Sem', firstDayOfWeek: 1, dateFormat: 'dd/mm/yy',
    weak: 'Débil', medium: 'Media', strong: 'Fuerte', passwordPrompt: 'Escribe una contraseña',
    emptyFilterMessage: 'Sin resultados', emptyMessage: 'Sin opciones', emptySearchMessage: 'Sin coincidencias', emptySelectionMessage: 'Sin selección',
    searchMessage: '{0} resultados', selectionMessage: '{0} seleccionados',
};

createInertiaApp({
    title: (title) => (title ? `${title} | SIGMA24` : 'SIGMA24 Agenda'),
    resolve: (name) => resolvePageComponent(`./Pages/${name}.vue`, import.meta.glob('./Pages/**/*.vue')),
    setup({ el, App, props, plugin }) {
        const app = createApp({ render: () => h(App, props) })
            .use(plugin)
            .use(ZiggyVue)
            .use(PrimeVue, { theme: { preset: Sigma24, options: { darkModeSelector: '.app-dark', cssLayer: false } }, locale: localeEs })
            .use(ToastService)
            .use(ConfirmationService)
            .directive('tooltip', Tooltip);
        const comps = { Button, Dialog, Drawer, Select, InputText, InputNumber, Textarea, Checkbox, ToggleSwitch, SelectButton, DatePicker,
            DataTable, Column, Tag, RadioButton, Password, Paginator, Tabs, TabList, Tab, Message, IconField, InputIcon };
        Object.entries(comps).forEach(([n, c]) => app.component(n, c));
        return app.mount(el);
    },
    progress: { color: '#E9A23B' },
});
