/**
 * DependencyEditor Component
 *
 * Editor for skill dependencies: requires, suggests, conflicts.
 */
import { useState, useMemo } from '@wordpress/element';
import { __ } from '@wordpress/i18n';
import PropTypes from 'prop-types';

const DEPENDENCY_TYPES = [
	{
		id: 'requires',
		label: 'Requires',
		icon: '',
		color: 'success',
		description: 'Skills that must be loaded before this one',
	},
	{
		id: 'suggests',
		label: 'Suggests',
		icon: '',
		color: 'default',
		description: 'Skills recommended to load after this one',
	},
	{
		id: 'conflicts',
		label: 'Conflicts',
		icon: '',
		color: 'error',
		description: 'Skills that cannot be used together with this one',
	},
];

export default function DependencyEditor({
	allSkills = [],
	requires = [],
	suggests = [],
	conflicts = [],
	onRequiresChange,
	onSuggestsChange,
	onConflictsChange,
	currentSkillId,
}) {
	const [activeType, setActiveType] = useState('requires');
	const [searchQuery, setSearchQuery] = useState('');

	// Get current list based on active type
	const currentList = useMemo(() => {
		if (activeType === 'requires') {
			return requires;
		}
		if (activeType === 'suggests') {
			return suggests;
		}
		if (activeType === 'conflicts') {
			return conflicts;
		}
		return [];
	}, [activeType, requires, suggests, conflicts]);

	// Get available skills (excluding current and already selected)
	const availableSkills = useMemo(() => {
		const allSelected = [...requires, ...suggests, ...conflicts];

		return allSkills.filter((skill) => {
			// Exclude current skill
			if (
				skill.id === currentSkillId ||
				skill.name === currentSkillId
			) {
				return false;
			}
			// Exclude already selected in any list
			if (
				allSelected.includes(skill.id) ||
				allSelected.includes(skill.name)
			) {
				return false;
			}
			// Apply search filter
			if (searchQuery) {
				const query = searchQuery.toLowerCase();
				return (
					skill.name?.toLowerCase().includes(query) ||
					skill.display_name?.toLowerCase().includes(query)
				);
			}
			return true;
		});
	}, [
		allSkills,
		requires,
		suggests,
		conflicts,
		currentSkillId,
		searchQuery,
	]);

	// Add skill to current list
	const addSkill = (skillId) => {
		if (activeType === 'requires') {
			onRequiresChange([...requires, skillId]);
		} else if (activeType === 'suggests') {
			onSuggestsChange([...suggests, skillId]);
		} else if (activeType === 'conflicts') {
			onConflictsChange([...conflicts, skillId]);
		}
		setSearchQuery('');
	};

	// Remove skill from current list
	const removeSkill = (skillId) => {
		if (activeType === 'requires') {
			onRequiresChange(requires.filter((id) => id !== skillId));
		} else if (activeType === 'suggests') {
			onSuggestsChange(suggests.filter((id) => id !== skillId));
		} else if (activeType === 'conflicts') {
			onConflictsChange(conflicts.filter((id) => id !== skillId));
		}
	};

	// Move skill between lists
	const moveSkill = (skillId, toType) => {
		// Remove from current
		removeSkill(skillId);

		// Add to target
		if (toType === 'requires') {
			onRequiresChange([...requires, skillId]);
		} else if (toType === 'suggests') {
			onSuggestsChange([...suggests, skillId]);
		} else if (toType === 'conflicts') {
			onConflictsChange([...conflicts, skillId]);
		}
	};

	const activeConfig = DEPENDENCY_TYPES.find((t) => t.id === activeType);

	return (
		<div className="flex flex-col gap-6">
			{ /* Type tabs */}
			<div className="flex bg-gray-100/50 dark:bg-gray-800/50 p-1.5 rounded-xl border border-gray-200 dark:border-gray-700">
				{DEPENDENCY_TYPES.map((type) => (
					<button
						key={type.id}
						type="button"
						className={`flex-1 flex items-center justify-center gap-2 py-2 px-3 text-sm font-medium rounded-lg transition-all ${activeType === type.id
								? `bg-white dark:bg-gray-700 shadow-sm ${type.color === 'success' ? 'text-green-600 dark:text-green-400' :
									type.color === 'error' ? 'text-red-600 dark:text-red-400' :
										'text-gray-900 dark:text-gray-100'
								}`
								: 'text-gray-500 hover:text-gray-700 dark:text-gray-400 dark:hover:text-gray-300'
							}`}
						onClick={() => setActiveType(type.id)}
					>
						<span>{type.label}</span>
						<span className={`inline-flex items-center justify-center w-5 h-5 text-xs rounded-full ${activeType === type.id
								? `bg-current/10`
								: 'bg-gray-200 dark:bg-gray-700'
							}`}>
							{type.id === 'requires'
								? requires.length
								: type.id === 'suggests'
									? suggests.length
									: conflicts.length}
						</span>
					</button>
				))}
			</div>

			{ /* Description */}
			<p className="text-sm text-gray-500 dark:text-gray-400 m-0 -mt-3 italic px-1">
				{activeConfig?.icon} {activeConfig?.description}
			</p>

			{ /* Current dependencies */}
			<div className="bg-gray-50/50 dark:bg-gray-900/50 rounded-xl border border-gray-200 dark:border-gray-700 min-h-[120px] p-4">
				{currentList.length > 0 ? (
					<div className="flex flex-col gap-2">
						{currentList.map((skillId) => {
							const skill = allSkills.find(
								(s) => s.id === skillId || s.name === skillId
							);
							return (
								<div
									key={skillId}
									className={`flex items-center justify-between p-3 bg-white dark:bg-gray-800 rounded-lg border shadow-sm transition-all hover:border-gray-300 dark:hover:border-gray-600 ${activeConfig?.color === 'success' ? 'border-green-200 dark:border-green-800/50' :
											activeConfig?.color === 'error' ? 'border-red-200 dark:border-red-800/50' :
												'border-gray-200 dark:border-gray-700'
										}`}
								>
									<span className="font-medium text-sm text-gray-900 dark:text-white">
										{skill?.display_name ||
											skill?.name ||
											skillId}
									</span>
									<div className="flex items-center gap-1 opacity-60 hover:opacity-100 transition-opacity">
										{ /* Move buttons */}
										{DEPENDENCY_TYPES.filter(
											(t) => t.id !== activeType
										).map((type) => (
											<button
												key={type.id}
												type="button"
												className="p-1.5 text-gray-400 hover:text-gray-700 dark:hover:text-gray-300 hover:bg-gray-100 dark:hover:bg-gray-700 rounded transition-colors text-xs font-medium focus:outline-none"
												title={`Move to ${type.label}`}
												onClick={() =>
													moveSkill(
														skillId,
														type.id
													)
												}
											>
												{type.label.substring(0, 3)}
											</button>
										))}
										<div className="w-px h-4 bg-gray-200 dark:bg-gray-700 mx-1"></div>
										<button
											type="button"
											className="p-1.5 text-red-500 hover:text-white hover:bg-red-500 rounded transition-colors focus:outline-none"
											onClick={() =>
												removeSkill(skillId)
											}
											title={__('Remove', 'smart-woo-chatbot')}
										>
											<svg className="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
												<path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M6 18L18 6M6 6l12 12" />
											</svg>
										</button>
									</div>
								</div>
							);
						})}
					</div>
				) : (
					<div className="h-full flex flex-col items-center justify-center text-gray-400 dark:text-gray-500 p-6 text-sm">
						<svg className="w-8 h-8 mb-2 opacity-50" fill="none" viewBox="0 0 24 24" stroke="currentColor">
							<path strokeLinecap="round" strokeLinejoin="round" strokeWidth={1.5} d="M19 11H5m14 0a2 2 0 012 2v6a2 2 0 01-2 2H5a2 2 0 01-2-2v-6a2 2 0 012-2m14 0V9a2 2 0 00-2-2M5 11V9a2 2 0 002-2m0 0V5a2 2 0 012-2h6a2 2 0 012 2v2M7 7h10" />
						</svg>
						{__(
							'No dependencies. Add skills below.',
							'smart-woo-chatbot'
						)}
					</div>
				)}
			</div>

			{ /* Add skill */}
			<div className="bg-white dark:bg-gray-800 rounded-xl border border-gray-200 dark:border-gray-700 p-5 shadow-sm">
				<h4 className="font-semibold text-gray-900 dark:text-white mb-4 mt-0">{__('Add Skill', 'smart-woo-chatbot')}</h4>
				<div className="relative mb-4">
					<div className="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
						<svg className="w-4 h-4 text-gray-400" fill="none" viewBox="0 0 24 24" stroke="currentColor">
							<path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z" />
						</svg>
					</div>
					<input
						type="text"
						className="block w-full pl-10 pr-3 py-2 border border-gray-300 dark:border-gray-600 rounded-lg bg-gray-50 dark:bg-gray-700 text-sm placeholder-gray-400 focus:outline-none focus:ring-1 focus:ring-primary focus:border-primary transition-colors text-gray-900 dark:text-white"
						value={searchQuery}
						onChange={(e) => setSearchQuery(e.target.value)}
						placeholder={__('Search skills…', 'smart-woo-chatbot')}
					/>
				</div>

				<div className="flex flex-col gap-2 max-h-[250px] overflow-y-auto pr-1">
					{availableSkills.slice(0, 10).map((skill) => (
						<button
							key={skill.id}
							type="button"
							className="group flex items-center justify-between w-full p-2.5 bg-white dark:bg-gray-800 border border-gray-200 dark:border-gray-700 rounded-lg hover:border-primary hover:bg-primary/5 dark:hover:bg-primary/10 transition-colors text-left focus:outline-none focus:ring-2 focus:ring-primary/20"
							onClick={() => addSkill(skill.id || skill.name)}
						>
							<div className="flex items-center gap-3 overflow-hidden">
								<span className="inline-flex items-center px-1.5 py-0.5 rounded text-[10px] font-medium bg-gray-100 text-gray-600 dark:bg-gray-700 dark:text-gray-300 capitalize flex-shrink-0 border border-gray-200 dark:border-gray-600">
									{skill.category || 'general'}
								</span>
								<span className="text-sm font-medium text-gray-700 dark:text-gray-300 truncate group-hover:text-primary transition-colors">
									{skill.display_name || skill.name}
								</span>
							</div>
							<div className="flex-shrink-0 w-6 h-6 rounded-full bg-gray-100 dark:bg-gray-700 text-gray-400 group-hover:bg-primary group-hover:text-white flex items-center justify-center transition-colors">
								<svg className="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
									<path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M12 6v6m0 0v6m0-6h6m-6 0H6" />
								</svg>
							</div>
						</button>
					))}

					{availableSkills.length === 0 && (
						<p className="text-center text-sm text-gray-500 dark:text-gray-400 py-4 m-0">
							{allSkills.length === 0
								? __(
									'No other skills available.',
									'smart-woo-chatbot'
								)
								: __(
									'No matching skills found.',
									'smart-woo-chatbot'
								)}
						</p>
					)}

					{availableSkills.length > 10 && (
						<p className="text-center text-xs text-gray-400 dark:text-gray-500 py-2 m-0 border-t border-gray-100 dark:border-gray-700 mt-2">
							+{availableSkills.length - 10}{' '}
							{__(
								'more. Type to filter.',
								'smart-woo-chatbot'
							)}
						</p>
					)}
				</div>
			</div>

			{ /* Validation warnings */}
			<ValidationWarnings
				requires={requires}
				suggests={suggests}
				conflicts={conflicts}
				allSkills={allSkills}
			/>
		</div>
	);
}

DependencyEditor.propTypes = {
	allSkills: PropTypes.array,
	requires: PropTypes.array,
	suggests: PropTypes.array,
	conflicts: PropTypes.array,
	onRequiresChange: PropTypes.func.isRequired,
	onSuggestsChange: PropTypes.func.isRequired,
	onConflictsChange: PropTypes.func.isRequired,
	currentSkillId: PropTypes.oneOfType([
		PropTypes.string,
		PropTypes.number,
	]),
};

/**
 * Show validation warnings for circular dependencies etc.
 * @param root0
 * @param root0.requires
 * @param root0.suggests
 * @param root0.conflicts
 * @param root0.allSkills
 */
function ValidationWarnings({ requires, suggests, conflicts, allSkills }) {
	const warnings = useMemo(() => {
		const warns = [];

		// Check for overlaps
		const conflictsSet = new Set(conflicts);

		requires.forEach((id) => {
			if (conflictsSet.has(id)) {
				warns.push({
					type: 'error',
					message: `"${id}" cannot be both required AND conflicting`,
				});
			}
		});

		suggests.forEach((id) => {
			if (conflictsSet.has(id)) {
				warns.push({
					type: 'warning',
					message: `"${id}" is suggested but also marked as conflicting`,
				});
			}
		});

		// Check for circular requires (simple check)
		requires.forEach((reqId) => {
			const reqSkill = allSkills.find(
				(s) => s.id === reqId || s.name === reqId
			);
			if (reqSkill?.requires?.includes(reqId)) {
				warns.push({
					type: 'error',
					message: `Circular dependency detected with "${reqId}"`,
				});
			}
		});

		return warns;
	}, [requires, suggests, conflicts, allSkills]);

	if (warnings.length === 0) {
		return null;
	}

	return (
		<div className="flex flex-col gap-2 mt-4">
			{warnings.map((warn, idx) => (
				<div
					key={idx}
					className={`flex items-center gap-2 p-3 rounded-lg text-sm font-medium border ${warn.type === 'error'
							? 'bg-red-50 text-red-700 border-red-200 dark:bg-red-900/20 dark:text-red-300 dark:border-red-800/50'
							: 'bg-yellow-50 text-yellow-800 border-yellow-200 dark:bg-yellow-900/20 dark:text-yellow-300 dark:border-yellow-800/50'
						}`}
				>
					{warn.type === 'error' ? (
						<svg className="w-5 h-5 flex-shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor">
							<path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
						</svg>
					) : (
						<svg className="w-5 h-5 flex-shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor">
							<path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z" />
						</svg>
					)}
					{warn.message}
				</div>
			))}
		</div>
	);
}

ValidationWarnings.propTypes = {
	requires: PropTypes.array,
	suggests: PropTypes.array,
	conflicts: PropTypes.array,
	allSkills: PropTypes.array,
};
