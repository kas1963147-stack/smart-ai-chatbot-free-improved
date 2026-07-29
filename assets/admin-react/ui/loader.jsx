import PropTypes from 'prop-types';
import { cn } from './utils';

export default function Loader({ size = 'md', className = '', ...props }) {
	const sizeMap = {
		sm: 'h-4 w-4 border-2',
		md: 'h-6 w-6 border-2',
		lg: 'h-8 w-8 border-[3px]',
	};

	return (
		<div
			className={cn(
				'inline-block animate-spin rounded-full border-primary border-t-transparent',
				sizeMap[size] || sizeMap.md,
				className
			)}
			{...props}
		/>
	);
}

Loader.propTypes = {
	size: PropTypes.string,
	className: PropTypes.string,
};
