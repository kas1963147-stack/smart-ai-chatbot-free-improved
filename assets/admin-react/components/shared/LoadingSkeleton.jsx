/**
 * LoadingSkeleton Component
 *
 * Shimmer loading placeholder for content.
 */

import PropTypes from 'prop-types';

export function SkeletonCard() {
	return (
		<div className="swc-card">
			<div className="swc-flex swc-gap-4">
				<div className="swc-skeleton swc-skeleton--avatar"></div>
				<div className="swc-flex-1">
					<div className="swc-skeleton swc-skeleton--title"></div>
					<div
						className="swc-skeleton swc-skeleton--text"
						style={ { width: '40%' } }
					></div>
				</div>
			</div>
			<div className="swc-mt-4">
				<div className="swc-skeleton swc-skeleton--text"></div>
				<div
					className="swc-skeleton swc-skeleton--text"
					style={ { width: '80%' } }
				></div>
			</div>
			<div className="swc-flex swc-justify-between swc-mt-4">
				<div
					className="swc-skeleton"
					style={ {
						width: '80px',
						height: '24px',
						borderRadius: 'var(--swc-radius-full)',
					} }
				></div>
				<div className="swc-skeleton swc-skeleton--button"></div>
			</div>
		</div>
	);
}

export function SkeletonGrid( { count = 3 } ) {
	return (
		<div className="swc-grid swc-grid--2">
			{ Array.from( { length: count } ).map( ( _, i ) => (
				<SkeletonCard key={ i } />
			) ) }
		</div>
	);
}

export function SkeletonText( { lines = 3 } ) {
	return (
		<div>
			{ Array.from( { length: lines } ).map( ( _, i ) => (
				<div
					key={ i }
					className="swc-skeleton swc-skeleton--text"
					style={ { width: i === lines - 1 ? '60%' : '100%' } }
				></div>
			) ) }
		</div>
	);
}

export default function LoadingSkeleton( { type = 'card', count = 1 } ) {
	if ( type === 'grid' ) {
		return <SkeletonGrid count={ count } />;
	}

	if ( type === 'text' ) {
		return <SkeletonText lines={ count } />;
	}

	return <SkeletonCard />;
}

SkeletonGrid.propTypes = {
	count: PropTypes.number,
};

SkeletonText.propTypes = {
	lines: PropTypes.number,
};

LoadingSkeleton.propTypes = {
	type: PropTypes.string,
	count: PropTypes.number,
};
