import { useState, useRef, useEffect, useMemo } from '@wordpress/element';
import PropTypes from 'prop-types';
import { cn } from './utils';
import { ChevronDown, X, Check, Search } from 'lucide-react';

export default function MultiSelect({
	label,
	options = [],
	value = [],
	onChange,
	placeholder = 'Select...',
	help,
	className = '',
}) {
	const [isOpen, setIsOpen] = useState(false);
	const [searchTerm, setSearchTerm] = useState('');
	const containerRef = useRef(null);

	// Click outside to close
	useEffect(() => {
		const handleClickOutside = (event) => {
			if (containerRef.current && !containerRef.current.contains(event.target)) {
				setIsOpen(false);
			}
		};
		document.addEventListener('mousedown', handleClickOutside);
		return () => document.removeEventListener('mousedown', handleClickOutside);
	}, []);

	const filteredOptions = useMemo(() => {
		if (!searchTerm) {
            // Put disabled options empty options first, and filter down the rest if necessary
            return options;
        }
		return options.filter((opt) =>
			opt.label.toLowerCase().includes(searchTerm.toLowerCase())
		);
	}, [options, searchTerm]);

	const selectedOptions = useMemo(() => {
		return options.filter((opt) => value.includes(opt.value) && opt.value !== '');
	}, [options, value]);

	const handleSelect = (option, e) => {
		e.stopPropagation();
		if (option.disabled) return;
		
		let newValue;
		if (value.includes(option.value)) {
			newValue = value.filter((v) => v !== option.value);
		} else {
			newValue = [...value, option.value];
		}
		onChange(newValue);
	};

	const removeOption = (optionValue, e) => {
		e.stopPropagation();
		onChange(value.filter((v) => v !== optionValue));
	};

	return (
		<div className={cn('block w-full space-y-1', className)} ref={containerRef}>
			{label && <span className="text-sm font-medium text-slate-700 dark:text-slate-200">{label}</span>}
			
			<div className="relative">
				{/* Trigger button */}
				<div
					className={cn(
						'min-h-[42px] flex flex-wrap items-center gap-1.5 p-2 rounded-lg border border-slate-200 bg-white shadow-sm cursor-pointer transition focus-within:border-primary focus-within:ring-2 focus-within:ring-primary/20 dark:border-slate-800 dark:bg-slate-950',
						isOpen ? 'border-primary ring-2 ring-primary/20' : ''
					)}
					onClick={() => {
                        setIsOpen(!isOpen);
                        if (!isOpen) setSearchTerm('');
                    }}
				>
					{selectedOptions.length === 0 ? (
						<span className="text-sm text-slate-400 px-1 py-0.5">{placeholder}</span>
					) : (
						selectedOptions.map((opt) => (
							<span
								key={opt.value}
								className="inline-flex items-center gap-1 overflow-hidden rounded bg-primary/10 px-2 py-1 text-xs font-medium text-primary dark:bg-primary/20"
								onClick={(e) => e.stopPropagation()}
							>
								<span className="truncate max-w-[150px]">{opt.label}</span>
								<button
									type="button"
									className="rounded-full hover:bg-primary/20 p-0.5 text-primary focus:outline-none"
									onClick={(e) => removeOption(opt.value, e)}
								>
									<X className="h-3 w-3" />
								</button>
							</span>
						))
					)}
					<div className="ml-auto text-slate-400">
						<ChevronDown className="h-4 w-4" />
					</div>
				</div>

				{/* Dropdown Menu */}
				{isOpen && (
					<div className="absolute z-50 mt-1 w-full rounded-lg border border-slate-200 bg-white shadow-xl dark:border-slate-800 dark:bg-slate-900 overflow-hidden">
						<div className="flex items-center gap-2 px-3 py-2 border-b border-slate-100 dark:border-slate-800">
							<Search className="h-4 w-4 text-slate-400" />
							<input
								type="text"
								className="w-full bg-transparent text-sm outline-none placeholder:text-slate-400 dark:text-slate-200"
								placeholder="Search pages/posts..."
								value={searchTerm}
								onChange={(e) => setSearchTerm(e.target.value)}
								onClick={(e) => e.stopPropagation()}
								autoFocus
							/>
						</div>
						
						<div className="max-h-60 overflow-y-auto p-1">
							{filteredOptions.length === 0 ? (
								<div className="py-6 text-center text-sm text-slate-500">
									No results found
								</div>
							) : (
								filteredOptions.map((opt) => {
									if (opt.disabled) return null; // We don't need to show empty placeholder options in the dynamic menu
									
									const isSelected = value.includes(opt.value);
									return (
										<div
											key={opt.value}
											className={cn(
												'flex cursor-pointer items-center justify-between rounded-md px-3 py-2 text-sm transition',
												isSelected
													? 'bg-primary/5 text-primary'
													: 'text-slate-700 hover:bg-slate-100 dark:text-slate-200 dark:hover:bg-slate-800'
											)}
											onClick={(e) => handleSelect(opt, e)}
										>
											<span className="truncate">{opt.label}</span>
											{isSelected && <Check className="h-4 w-4" />}
										</div>
									);
								})
							)}
						</div>
					</div>
				)}
			</div>
			
			{help && <span className="text-xs text-slate-500 dark:text-slate-400">{help}</span>}
		</div>
	);
}

MultiSelect.propTypes = {
	label: PropTypes.node,
	options: PropTypes.arrayOf(
		PropTypes.shape({
			value: PropTypes.string,
			label: PropTypes.string,
			disabled: PropTypes.bool,
		})
	),
	value: PropTypes.arrayOf(PropTypes.string),
	onChange: PropTypes.func.isRequired,
	placeholder: PropTypes.string,
	help: PropTypes.node,
	className: PropTypes.string,
};
