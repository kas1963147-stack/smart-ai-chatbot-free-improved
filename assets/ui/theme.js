import { createTheme } from '@mantine/core';

const swcPrimary = [
	'var(--swc-primary-50)',
	'var(--swc-primary-100)',
	'var(--swc-primary-200)',
	'var(--swc-primary-300)',
	'var(--swc-primary-400)',
	'var(--swc-primary-500)',
	'var(--swc-primary-600)',
	'var(--swc-primary-700)',
	'var(--swc-primary-800)',
	'var(--swc-primary-900)',
];

const swcGray = [
	'var(--swc-gray-50)',
	'var(--swc-gray-100)',
	'var(--swc-gray-200)',
	'var(--swc-gray-300)',
	'var(--swc-gray-400)',
	'var(--swc-gray-500)',
	'var(--swc-gray-600)',
	'var(--swc-gray-700)',
	'var(--swc-gray-800)',
	'var(--swc-gray-900)',
];

const swcSuccess = [
	'var(--swc-success-50)',
	'var(--swc-success-100)',
	'var(--swc-success-100)',
	'var(--swc-success-100)',
	'var(--swc-success-500)',
	'var(--swc-success-500)',
	'var(--swc-success-600)',
	'var(--swc-success-700)',
	'var(--swc-success-700)',
	'var(--swc-success-700)',
];

const swcWarning = [
	'var(--swc-warning-50)',
	'var(--swc-warning-100)',
	'var(--swc-warning-100)',
	'var(--swc-warning-100)',
	'var(--swc-warning-500)',
	'var(--swc-warning-500)',
	'var(--swc-warning-600)',
	'var(--swc-warning-700)',
	'var(--swc-warning-700)',
	'var(--swc-warning-700)',
];

const swcError = [
	'var(--swc-error-50)',
	'var(--swc-error-100)',
	'var(--swc-error-100)',
	'var(--swc-error-100)',
	'var(--swc-error-500)',
	'var(--swc-error-500)',
	'var(--swc-error-600)',
	'var(--swc-error-700)',
	'var(--swc-error-700)',
	'var(--swc-error-700)',
];

const swcInfo = [
	'var(--swc-info-50)',
	'var(--swc-info-100)',
	'var(--swc-info-100)',
	'var(--swc-info-100)',
	'var(--swc-info-500)',
	'var(--swc-info-500)',
	'var(--swc-info-600)',
	'var(--swc-info-700)',
	'var(--swc-info-700)',
	'var(--swc-info-700)',
];

export const swcTheme = createTheme({
	colors: {
		swc: swcPrimary,
		gray: swcGray,
		success: swcSuccess,
		warning: swcWarning,
		error: swcError,
		info: swcInfo,
	},
	primaryColor: 'swc',
	fontFamily: 'var(--swc-font-sans)',
	fontFamilyMonospace: 'var(--swc-font-mono)',
	radius: {
		xs: 'var(--swc-radius-sm)',
		sm: 'var(--swc-radius-md)',
		md: 'var(--swc-radius-lg)',
		lg: 'var(--swc-radius-xl)',
		xl: 'var(--swc-radius-2xl)',
	},
	spacing: {
		xs: 'var(--swc-space-2)',
		sm: 'var(--swc-space-3)',
		md: 'var(--swc-space-4)',
		lg: 'var(--swc-space-6)',
		xl: 'var(--swc-space-8)',
	},
	shadows: {
		xs: 'var(--swc-shadow-xs)',
		sm: 'var(--swc-shadow-sm)',
		md: 'var(--swc-shadow-md)',
		lg: 'var(--swc-shadow-lg)',
		xl: 'var(--swc-shadow-xl)',
	},
});
