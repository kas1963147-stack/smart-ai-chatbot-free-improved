import { useEffect } from '@wordpress/element';
import { createPortal } from '@wordpress/element';
import PropTypes from 'prop-types';
import { cn } from './utils';

const SIZE_MAP = {
	xs: 'max-w-sm',
	sm: 'max-w-md',
	md: 'max-w-lg',
	lg: 'max-w-2xl',
	xl: 'max-w-4xl',
};

export default function Modal({
	isOpen,
	onClose,
	title,
	subtitle,
	children,
	footer,
	size = 'md',
	className = '',
	...props
}) {
	// Lock body scroll when modal is open
	useEffect(() => {
		if (!isOpen) return;

		const html = document.documentElement;
		const body = document.body;
		const scrollY = window.scrollY;

		// Save current scroll and lock
		html.style.overflow = 'hidden';
		body.style.overflow = 'hidden';
		body.style.position = 'fixed';
		body.style.top = `-${scrollY}px`;
		body.style.width = '100%';

		return () => {
			// Restore scroll
			html.style.overflow = '';
			body.style.overflow = '';
			body.style.position = '';
			body.style.top = '';
			body.style.width = '';
			window.scrollTo(0, scrollY);
		};
	}, [isOpen]);

	// Close on Escape key
	useEffect(() => {
		if (!isOpen) return;

		const handleKeyDown = (e) => {
			if (e.key === 'Escape') {
				onClose();
			}
		};

		document.addEventListener('keydown', handleKeyDown);
		return () => document.removeEventListener('keydown', handleKeyDown);
	}, [isOpen, onClose]);

	if (!isOpen) {
		return null;
	}

	// Use createPortal to render at document.body level,
	// escaping any parent stacking contexts (e.g. sticky header z-30)
	return createPortal(
		<div className="fixed inset-0 z-[9999] flex items-center justify-center px-4 py-6">
			{/* Overlay */}
			<button
				type="button"
				className="absolute inset-0 bg-[#071437]/50 backdrop-blur-[2px] cursor-default"
				aria-label="Close modal"
				onClick={onClose}
			/>
			{/* Modal Container — flex column with max-height so header/footer pin and content scrolls */}
			<div
				className={cn(
					'relative z-10 w-full flex flex-col rounded-xl border border-gray-200 bg-white shadow-2xl dark:border-slate-800 dark:bg-slate-900 animate-in fade-in-0 zoom-in-95',
					'max-h-[calc(100vh-3rem)]',
					SIZE_MAP[size] || SIZE_MAP.md,
					className
				)}
				{...props}
			>
				{/* Header — sticky top */}
				{title && (
					<div className="flex items-center justify-between border-b border-gray-200 px-6 py-4 dark:border-slate-800 shrink-0">
						<div>
							<h3 className="text-[15px] font-semibold text-[#071437] dark:text-slate-100 tracking-[-0.01em]">
								{title}
							</h3>
							{subtitle && (
								<p className="mt-0.5 text-[13px] text-[#78829D] dark:text-slate-400">
									{subtitle}
								</p>
							)}
						</div>
						<button
							type="button"
							onClick={onClose}
							className="flex items-center justify-center w-8 h-8 rounded-lg text-[#99A1B7] hover:text-[#071437] hover:bg-[#F5F8FA] transition-colors dark:text-slate-400 dark:hover:text-slate-200 dark:hover:bg-slate-800"
						>
							<svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2.5" strokeLinecap="round" strokeLinejoin="round">
								<path d="M18 6 6 18" />
								<path d="m6 6 12 12" />
							</svg>
						</button>
					</div>
				)}

				{/* Content — scrollable */}
				<div className="px-6 py-5 overflow-y-auto flex-1 min-h-0">{children}</div>

				{/* Footer — sticky bottom */}
				{footer && (
					<div className="flex items-center justify-end gap-3 border-t border-gray-200 bg-[#F5F8FA] px-6 py-4 rounded-b-xl dark:border-slate-800 dark:bg-slate-800/50 shrink-0">
						{footer}
					</div>
				)}
			</div>
		</div>,
		document.body
	);
}

Modal.propTypes = {
	isOpen: PropTypes.bool,
	onClose: PropTypes.func.isRequired,
	title: PropTypes.node,
	subtitle: PropTypes.node,
	children: PropTypes.node,
	footer: PropTypes.node,
	size: PropTypes.string,
	className: PropTypes.string,
};
