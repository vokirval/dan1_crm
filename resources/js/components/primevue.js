import PrimeVue from 'primevue/config';
import { definePreset } from '@primevue/themes';

import Aura from '@primevue/themes/aura';
import Button from 'primevue/button';
import InputText from 'primevue/inputtext';
import Dialog from 'primevue/dialog';
import Tooltip from 'primevue/tooltip';
import DataTable from 'primevue/datatable';
import DatePicker from 'primevue/datepicker';
import Column from 'primevue/column';
import Paginator from 'primevue/paginator';
import Card from 'primevue/card';
import FloatLabel from 'primevue/floatlabel';
import Textarea from 'primevue/textarea';
import InputNumber from 'primevue/inputnumber';
import Select from 'primevue/select';
import Toast from 'primevue/toast';
import ToastService from 'primevue/toastservice';
import Tabs from 'primevue/tabs';
import TabList from 'primevue/tablist';
import Tab from 'primevue/tab';
import TabPanels from 'primevue/tabpanels';
import TabPanel from 'primevue/tabpanel';
import Tag from 'primevue/tag';
import ConfirmPopup from 'primevue/confirmpopup';
import ConfirmationService from 'primevue/confirmationservice';
import ColorPicker from 'primevue/colorpicker';
import Inplace from 'primevue/inplace';

import IftaLabel from 'primevue/iftalabel';
import Menu from 'primevue/menu';
import Badge from 'primevue/badge';
import Avatar from 'primevue/avatar';
import MultiSelect from 'primevue/multiselect';
import ToggleButton from 'primevue/togglebutton';
import ContextMenu from 'primevue/contextmenu';


const Noir = definePreset(Aura, {
    semantic: {
        primary: {
            50: '{zinc.50}',
            100: '{zinc.100}',
            200: '{zinc.200}',
            300: '{zinc.300}',
            400: '{zinc.400}',
            500: '{zinc.500}',
            600: '{zinc.600}',
            700: '{zinc.700}',
            800: '{zinc.800}',
            900: '{zinc.900}',
            950: '{zinc.950}'
        },
        colorScheme: {
            light: {
                primary: {
                    color: '{zinc.950}',
                    inverseColor: '#ffffff',
                    hoverColor: '{zinc.900}',
                    activeColor: '{zinc.800}'
                },
                highlight: {
                    background: '{zinc.950}',
                    focusBackground: '{zinc.700}',
                    color: '#ffffff',
                    focusColor: '#ffffff'
                }
            },
            dark: {
                primary: {
                    color: '{zinc.50}',
                    inverseColor: '{zinc.950}',
                    hoverColor: '{zinc.100}',
                    activeColor: '{zinc.200}'
                },
                highlight: {
                    background: 'rgba(250, 250, 250, .16)',
                    focusBackground: 'rgba(250, 250, 250, .24)',
                    color: 'rgba(255,255,255,.87)',
                    focusColor: 'rgba(255,255,255,.87)'
                }
            }
        }
    }
});

export function setupPrimeVue(app) {
    app.use(PrimeVue, {
        theme: {
            preset: Noir,
            options: {
                darkModeSelector: false,
                cssLayer: false
            }
        }
    });
    

    app.component('Button', Button);
    app.component('InputText', InputText);
    app.component('Dialog', Dialog);
    app.component('DataTable', DataTable);
    app.component('Column', Column);
    app.component('Paginator', Paginator);
    app.component('Card', Card);
    app.component('FloatLabel', FloatLabel);
    app.component('Textarea', Textarea);
    app.component('InputNumber', InputNumber);
    app.component('Select', Select);
    app.component('Toast', Toast);

    app.component('Tabs', Tabs);
    app.component('TabList', TabList);
    app.component('Tab', Tab);
    app.component('TabPanels', TabPanels);
    app.component('TabPanel', TabPanel);

    app.component('ConfirmPopup', ConfirmPopup);
    app.component('MultiSelect', MultiSelect);
    app.component('Tag', Tag);
    app.component('IftaLabel', IftaLabel);
    app.component('Menu', Menu);
    app.component('Badge', Badge);
    app.component('Avatar', Avatar);
    app.component('Inplace', Inplace);
    app.component('ColorPicker', ColorPicker);
    app.component('ToggleButton', ToggleButton);
    app.component('ContextMenu', ContextMenu);
    app.component('DatePicker', DatePicker);
    
    app.use(ToastService);
    app.use(ConfirmationService);
    app.directive('tooltip', Tooltip);
    
}
