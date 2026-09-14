/**
 * SkillTree Component
 *
 * Hierarchical tree view of skills with groups and parent/child relationships.
 */
import { useMemo } from '@wordpress/element';
import { __ } from '@wordpress/i18n';

// Build tree structure from flat skills array
const buildTree = (skills, groups) => {
	const tree = [];
	const byId = {};
	const byGroup = {};

	// Index skills by ID
	skills.forEach((skill) => {
		byId[skill.id] = { ...skill, children: [] };
	});

	// Build parent-child relationships
	skills.forEach((skill) => {
		if (skill.parent_skill && byId[skill.parent_skill]) {
			byId[skill.parent_skill].children.push(byId[skill.id]);
		}
	});

	// Group skills by group ID
	skills.forEach((skill) => {
		const groupId = skill.group || 'ungrouped';
		if (!byGroup[groupId]) {
			byGroup[groupId] = [];
		}
		// Only add if no parent (top level in group)
		if (!skill.parent_skill) {
			byGroup[groupId].push(byId[skill.id]);
		}
	});

	// Add groups with their skills
	groups.forEach((group) => {
		tree.push({
			type: 'group',
			id: group.id,
			name: group.name,
			icon: group.icon,
			color: group.color,
			children: byGroup[group.id] || [],
		});
	});

	// Add ungrouped skills
	if (byGroup.ungrouped?.length > 0) {
		tree.push({
			type: 'group',
			id: 'ungrouped',
			name: __('Ungrouped', 'agentflow-ai'),
			icon: '',
			color: '#6b7280',
			children: byGroup.ungrouped,
		});
	}

	return tree;
};

export default function SkillTree({
	skills,
	groups,
	onSelectSkill,
	onSelectGroup,
	selectedId,
	expandedGroups = {},
	onToggleGroup,
}) {
	const tree = useMemo(
		() => buildTree(skills, groups),
		[skills, groups]
	);

	return (
		<div className="flex flex-col gap-1 w-full text-sm">
			{tree.map((node) => (
				<TreeNode
					key={node.id}
					node={node}
					depth={0}
					selectedId={selectedId}
					expandedGroups={expandedGroups}
					onSelectSkill={onSelectSkill}
					onSelectGroup={onSelectGroup}
					onToggleGroup={onToggleGroup}
				/>
			))}

			{tree.length === 0 && (
				<div className="flex flex-col items-center justify-center p-8 bg-gray-50 dark:bg-gray-800/50 rounded-xl border border-dashed border-gray-300 dark:border-gray-700 text-gray-400 dark:text-gray-500 text-sm text-center">
					<svg className="w-8 h-8 mb-2 opacity-50" fill="none" viewBox="0 0 24 24" stroke="currentColor">
						<path strokeLinecap="round" strokeLinejoin="round" strokeWidth={1.5} d="M19 11H5m14 0a2 2 0 012 2v6a2 2 0 01-2 2H5a2 2 0 01-2-2v-6a2 2 0 012-2m14 0V9a2 2 0 00-2-2M5 11V9a2 2 0 002-2m0 0V5a2 2 0 012-2h6a2 2 0 012 2v2M7 7h10" />
					</svg>
					{__('No skills or groups yet', 'agentflow-ai')}
				</div>
			)}
		</div>
	);
}

function TreeNode({
	node,
	depth,
	selectedId,
	expandedGroups,
	onSelectSkill,
	onSelectGroup,
	onToggleGroup,
}) {
	const isGroup = node.type === 'group';
	const isExpanded = expandedGroups[node.id] !== false; // Default expanded
	const isSelected = selectedId === node.id;
	const hasChildren = node.children && node.children.length > 0;

	const handleClick = (e) => {
		e.stopPropagation();
		if (isGroup) {
			onSelectGroup?.(node);
		} else {
			onSelectSkill?.(node);
		}
	};

	const handleToggle = (e) => {
		e.stopPropagation();
		onToggleGroup?.(node.id);
	};

	return (
		<div className="w-full">
			<div
				className={`group flex items-center pr-3 py-2 cursor-pointer transition-all rounded-lg select-none ${isSelected
						? 'bg-primary/10 text-primary dark:bg-primary/20 dark:text-blue-400'
						: 'hover:bg-gray-100 dark:hover:bg-gray-800 text-gray-700 dark:text-gray-300'
					} ${isGroup ? 'font-medium' : ''}`}
				style={{ paddingLeft: `${depth * 20 + 8}px` }}
				onClick={handleClick}
			>
				{ /* Expand toggle */}
				<div
					className="w-5 h-5 flex items-center justify-center mr-1.5 flex-shrink-0"
					onClick={hasChildren ? handleToggle : undefined}
				>
					{hasChildren ? (
						<button
							className="text-gray-400 hover:text-gray-600 dark:hover:text-gray-300 transition-colors focus:outline-none"
						>
							<svg className={`w-3 h-3 transition-transform ${isExpanded ? 'rotate-90' : ''}`} fill="none" viewBox="0 0 24 24" stroke="currentColor">
								<path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M9 5l7 7-7 7" />
							</svg>
						</button>
					) : (
						<div className="w-1 h-3 border-l-2 border-gray-300 dark:border-gray-600 rounded-full ml-1" />
					)}
				</div>

				{ /* Icon */}
				{(isGroup && node.icon) ? (
					<span
						className="flex items-center justify-center w-5 h-5 mr-2"
						style={node.color ? { color: node.color } : {}}
					>
						{node.icon}
					</span>
				) : (
					<span className={`flex items-center justify-center w-5 h-5 mr-2 opacity-70 ${isSelected ? 'text-primary' : 'text-gray-400'}`}>
						{isGroup ? (
							<svg className="w-4 h-4" fill="currentColor" viewBox="0 0 20 20"><path fillRule="evenodd" d="M2 6a2 2 0 012-2h4l2 2h6a2 2 0 012 2v8a2 2 0 01-2 2H4a2 2 0 01-2-2V6zm12.5 1.5a.5.5 0 00-.5-.5h-8a.5.5 0 00-.5.5v2a.5.5 0 00.5.5h8a.5.5 0 00.5-.5v-2zm0 4a.5.5 0 00-.5-.5h-8a.5.5 0 00-.5.5v2a.5.5 0 00.5.5h8a.5.5 0 00.5-.5v-2z" clipRule="evenodd" /></svg>
						) : (
							<svg className="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M10 20l4-16m4 4l4 4-4 4M6 16l-4-4 4-4" /></svg>
						)}
					</span>
				)}

				{ /* Name */}
				<span className="truncate flex-1">
					{node.name || node.display_name || node.id}
				</span>

				{ /* Count for groups */}
				{isGroup && hasChildren && (
					<span className="ml-2 px-1.5 py-0.5 text-[10px] font-bold bg-gray-100 dark:bg-gray-700 text-gray-500 dark:text-gray-400 rounded-md">
						{node.children.length}
					</span>
				)}

				{ /* Always on badge */}
				{!isGroup && node.always_on && (
					<span className="ml-2 inline-flex items-center px-1.5 py-0.5 rounded text-[10px] font-medium bg-green-100 text-green-800 dark:bg-green-900/40 dark:text-green-300 border border-green-200 dark:border-green-800 flex-shrink-0">
						ON
					</span>
				)}
			</div>

			{ /* Children */}
			{hasChildren && isExpanded && (
				<div className="flex flex-col gap-0.5 mt-0.5 relative">
					<div className="absolute top-0 bottom-0 left-[21px] w-px bg-gray-200 dark:bg-gray-700" style={{ left: `${depth * 20 + 21}px` }} />
					{node.children.map((child) => (
						<TreeNode
							key={child.id}
							node={child}
							depth={depth + 1}
							selectedId={selectedId}
							expandedGroups={expandedGroups}
							onSelectSkill={onSelectSkill}
							onSelectGroup={onSelectGroup}
							onToggleGroup={onToggleGroup}
						/>
					))}
				</div>
			)}
		</div>
	);
}
