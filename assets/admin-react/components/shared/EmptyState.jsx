/**
 * EmptyState Component
 *
 * A reusable premium empty state component.
 * Used when there's no data to display.
 */
import PropTypes from 'prop-types';
import { Button } from '../ui';

export default function EmptyState( {
	icon = '—',
	title,
	description,
	action,
} ) {
	return (
		<div className="swc-empty swc-animate-in">
			<div className="swc-empty__icon">{ icon }</div>
			<h3 className="swc-empty__title">{ title }</h3>
			{ description && (
				<p className="swc-empty__desc">{ description }</p>
			) }
			{ action && (
				<Button
					variant="primary"
					size="lg"
					onClick={ action.onClick }
					icon={ action.icon ? <span>{ action.icon }</span> : null }
				>
					{ action.label }
				</Button>
			) }
		</div>
	);
}

EmptyState.propTypes = {
	icon: PropTypes.node,
	title: PropTypes.string.isRequired,
	description: PropTypes.string,
	action: PropTypes.shape( {
		icon: PropTypes.node,
		label: PropTypes.string,
		onClick: PropTypes.func,
	} ),
};
