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
import digitos from './lib/digitos';

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

// Paleta de SIGAM: azul marino #173a5f (primario) + verde azulado #1f9e86 (acento). Solo tema claro.
const Sigma24 = definePreset(Aura, {
    semantic: {
        primary: {
            50: '#eaf0f6', 100: '#dae5f0', 200: '#b6c9dd', 300: '#87a5c4', 400: '#4f77a0',
            500: '#173a5f', 600: '#0f2c4a', 700: '#0c243d', 800: '#091c30', 900: '#061424', 950: '#030b14',
        },
        colorScheme: {
            light: {
                primary: { color: '{primary.500}', contrastColor: '#ffffff', hoverColor: '{primary.600}', activeColor: '{primary.700}' },
                highlight: { background: 'rgba(23, 58, 95, 0.10)', focusBackground: 'rgba(23, 58, 95, 0.16)', color: '#173a5f', focusColor: '#173a5f' },
                formField: { borderColor: '#d9dfe7', hoverBorderColor: '#173a5f', focusBorderColor: '#173a5f', placeholderColor: '#94a3b8' },
            },
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
            .use(PrimeVue, { theme: { preset: Sigma24, options: { darkModeSelector: false, cssLayer: false } }, locale: localeEs })
            .use(ToastService)
            .use(ConfirmationService)
            .directive('tooltip', Tooltip)
            .directive('digitos', digitos);
        // Todas las listas desplegables permiten escribir para buscar: el buscador se enfoca al abrir la lista.
        const SelectBuscable = {
            extends: Select,
            props: {
                filter: { type: Boolean, default: true },
                autoFilterFocus: { type: Boolean, default: true },
                resetFilterOnHide: { type: Boolean, default: true },
                filterPlaceholder: { type: String, default: 'Escribe para buscar…' },
            },
        };
        const comps = { Button, Dialog, Drawer, Select: SelectBuscable, InputText, InputNumber, Textarea, Checkbox, ToggleSwitch, SelectButton, DatePicker,
            DataTable, Column, Tag, RadioButton, Password, Paginator, Tabs, TabList, Tab, Message, IconField, InputIcon };
        Object.entries(comps).forEach(([n, c]) => app.component(n, c));
        return app.mount(el);
    },
    progress: { color: '#1f9e86' },
});
