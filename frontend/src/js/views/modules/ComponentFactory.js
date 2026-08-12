import { BaseComponent } from '../components/BaseComponent.js';
import { AlertComponent } from '../components/Alert.js';
import { BreadcrumbComponent } from '../components/Breadcrumb.js';
import { ButtonComponent } from '../components/Button.js';
import { EmptyStateComponent } from '../components/EmptyState.js';
import { FormComponent } from '../components/Form.js';
import { FormFieldComponent } from '../components/FormField.js';
import { InputCheckComponent } from '../components/InputCheck.js';
import { InputDateComponent } from '../components/InputDate.js';
import { InputEmailComponent } from '../components/InputEmail.js';
import { InputFileComponent } from '../components/InputFile.js';
import { InputHiddenComponent } from '../components/InputHidden.js';
import { InputNumberComponent } from '../components/InputNumber.js';
import { InputPasswordComponent } from '../components/InputPassword.js';
import { InputRadioComponent } from '../components/InputRadio.js';
import { InputSelectComponent } from '../components/InputSelect.js';
import { InputTextComponent } from '../components/InputText.js';
import { InputTextAreaComponent } from '../components/InputTextArea.js';
import { InputTimeComponent } from '../components/InputTime.js';
import { InputSwitchComponent } from '../components/InputSwitch.js';
import { ModalComponent } from '../components/Modal.js';
import { PageComponent } from '../components/Page.js';
import { PageHeaderComponent } from '../components/PageHeader.js';
import { PasswordStrengthComponent } from '../components/PasswordStrength.js';
import { SectionComponent } from '../components/Section.js';
import { SkeletonComponent } from '../components/Skeleton.js';
import { SpinnerComponent } from '../components/Spinner.js';
import { TableComponent } from '../components/Table.js';
import { TabsComponent } from '../components/Tabs.js';
import { TypographyComponent } from '../components/Typography.js';

function createFactory(ComponentClass, tagName = 'div') {
	return (options = {}) => ComponentClass.create(tagName, options);
}

function createFactoryWithTag(ComponentClass, tagNameResolver) {
	return (options = {}) => ComponentClass.create(
		typeof tagNameResolver === 'function' ? tagNameResolver(options) : tagNameResolver,
		options
	);
}

function defineComponent(name, ComponentClass, tagName, category, description) {
	return { name, ComponentClass, tagName, category, description };
}

const componentDefinitions = [
	defineComponent('base', BaseComponent, 'div', 'general',
		'Contenedor base que hereda de HTMLElement'),
	defineComponent('button', ButtonComponent, 'button', 'general',
		'Acciones primarias y secundarias'),
	defineComponent('typography', TypographyComponent, (options = {}) => options.as ?? 'p', 'general',
		'Textos y encabezados base'),
	defineComponent('page', PageComponent, 'section', 'layout',
		'Contenedor principal de página'),
	defineComponent('pageHeader', PageHeaderComponent, 'header', 'layout',
		'Cabecera de página con título y acciones'),
	defineComponent('section', SectionComponent, 'section', 'layout',
		'Bloque de contenido con títulos y subtítulos'),
	defineComponent('breadcrumb', BreadcrumbComponent, 'nav', 'navigation',
		'Ruta de navegación'),
	defineComponent('tabs', TabsComponent, 'div', 'navigation',
		'Navegación por pestañas'),
	defineComponent('input', InputTextComponent, 'input', 'dataEntry',
		'Campo de texto base'),
	defineComponent('inputText', InputTextComponent, 'input', 'dataEntry',
		'Campo de texto'),
	defineComponent('inputTextArea', InputTextAreaComponent, 'textarea', 'dataEntry',
		'Área de texto'),
	defineComponent('inputSelect', InputSelectComponent, 'select', 'dataEntry',
		'Selector'),
	defineComponent('inputDate', InputDateComponent, 'input', 'dataEntry',
		'Campo de fecha'),
	defineComponent('inputTime', InputTimeComponent, 'input', 'dataEntry',
		'Campo de hora'),
	defineComponent('inputNumber', InputNumberComponent, 'input', 'dataEntry',
		'Campo numérico'),
	defineComponent('inputEmail', InputEmailComponent, 'input', 'dataEntry',
		'Campo de email'),
	defineComponent('inputFile', InputFileComponent, 'input', 'dataEntry',
		'Selector de archivo'),
	defineComponent('inputHidden', InputHiddenComponent, 'input', 'dataEntry',
		'Campo de valor oculto'),
	defineComponent('inputPassword', InputPasswordComponent, 'input', 'dataEntry',
		'Campo de contraseña'),
	defineComponent('inputRadio', InputRadioComponent, 'input', 'dataEntry',
		'Opción tipo radio'),
	defineComponent('inputCheck', InputCheckComponent, 'input', 'dataEntry',
		'Checkbox'),
	defineComponent('inputSwitch', InputSwitchComponent, 'label', 'dataEntry',
		'Interruptor booleano'),
	defineComponent('form', FormComponent, 'form', 'dataEntry',
		'Formulario base'),
	defineComponent('formField', FormFieldComponent, 'div', 'dataEntry',
		'Wrapper de campo con etiqueta y ayuda'),
	defineComponent('table', TableComponent, 'div', 'dataDisplay',
		'Tabla de datos'),
	defineComponent('emptyState', EmptyStateComponent, 'div', 'dataDisplay',
		'Estado vacío con acción'),
	defineComponent('alert', AlertComponent, 'div', 'feedback',
		'Mensajes de estado y advertencia'),
	defineComponent('passwordStrength', PasswordStrengthComponent, 'div', 'feedback',
		'Indicador configurable de fortaleza de contraseña'),
	defineComponent('modal', ModalComponent, 'div', 'feedback',
		'Diálogos y confirmaciones'),
	defineComponent('spinner', SpinnerComponent, 'div', 'feedback',
		'Indicador de carga'),
	defineComponent('skeleton', SkeletonComponent, 'div', 'feedback',
		'Marcadores de carga'),
	...[
		['div', 'div'], ['span', 'span'], ['sectionTag', 'section'], ['nav', 'nav'],
		['ul', 'ul'], ['li', 'li'], ['ol', 'ol'], ['a', 'a'], ['formTag', 'form'],
		['selectTag', 'select'], ['tableElement', 'table'], ['option', 'option'],
		['label', 'label'], ['header', 'header'], ['h2', 'h2'], ['h3', 'h3'], ['h4', 'h4'],
		['p', 'p'], ['thead', 'thead'], ['tbody', 'tbody'], ['tr', 'tr'],
		['th', 'th'], ['td', 'td'], ['main', 'main'], ['aside', 'aside'], ['img', 'img'],
		['footer', 'footer'], ['i', 'i'],
	].map(([name, tagName]) => ({ name, ComponentClass: BaseComponent, tagName })),
];

const componentFactories = Object.fromEntries(
	componentDefinitions.map(({ name, ComponentClass, tagName }) => [
		name,
		typeof tagName === 'function'
			? createFactoryWithTag(ComponentClass, tagName)
			: createFactory(ComponentClass, tagName),
	]),
);

const componentCatalog = Object.freeze(componentDefinitions.reduce((catalog, definition) => {
	if (definition.category === undefined) {
		return catalog;
	}

	if (!Array.isArray(catalog[definition.category])) {
		catalog[definition.category] = [];
	}

	catalog[definition.category].push(Object.freeze({
		name: definition.name,
		description: definition.description,
	}));
	return catalog;
}, {}));

export class ComponentFactory {
	create(name, options = {}) {
		const factory = componentFactories[name];
		if (typeof factory === 'function') {
			return factory(options);
		}

		throw new Error(`ComponentFactory: component "${String(name)}" is not registered`);
	}

	getCatalog() {
		return componentCatalog;
	}
}

export const component = new ComponentFactory();
