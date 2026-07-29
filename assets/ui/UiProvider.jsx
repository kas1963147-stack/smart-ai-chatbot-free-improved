import { MantineProvider } from '@mantine/core';
import { Notifications } from '@mantine/notifications';
import PropTypes from 'prop-types';

import { swcTheme } from './theme';

const getInitialColorScheme = () => {
	if ( typeof window === 'undefined' ) {
		return 'light';
	}

	return window.swcChatbot?.settings?.dark_mode ? 'dark' : 'light';
};

export default function UiProvider( { children } ) {
	return (
		<MantineProvider
			theme={ swcTheme }
			defaultColorScheme={ getInitialColorScheme() }
			cssVariablesSelector="#smart-ai-chatbot-manager-root"
		>
			<Notifications position="top-right" />
			{ children }
		</MantineProvider>
	);
}

UiProvider.propTypes = {
	children: PropTypes.node,
};
