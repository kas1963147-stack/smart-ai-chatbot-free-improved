/**
 * SortableMemberList Component
 *
 * Provides drag-and-drop reordering for team members using @dnd-kit.
 * Part of the Agent Team Visual Composer feature.
 */
import { __ } from '@wordpress/i18n';
import {
	DndContext,
	closestCenter,
	KeyboardSensor,
	PointerSensor,
	useSensor,
	useSensors,
} from '@dnd-kit/core';
import {
	arrayMove,
	SortableContext,
	sortableKeyboardCoordinates,
	useSortable,
	verticalListSortingStrategy,
} from '@dnd-kit/sortable';
import { CSS } from '@dnd-kit/utilities';
import PropTypes from 'prop-types';
import { Button, IconButton, Select, TextField } from '../ui';

const MEMBER_ROLES = [
	{ value: 'primary', label: __('Primary', 'agentflow-ai') },
	{ value: 'specialist', label: __('Specialist', 'agentflow-ai') },
	{ value: 'fallback', label: __('Fallback', 'agentflow-ai') },
];

/**
 * Individual sortable member card
 * @param root0
 * @param root0.member
 * @param root0.index
 * @param root0.orchestrationMode
 * @param root0.onUpdate
 * @param root0.onRemove
 */
function SortableMemberCard({
	member,
	index,
	orchestrationMode,
	onUpdate,
	onRemove,
}) {
	const {
		attributes,
		listeners,
		setNodeRef,
		transform,
		transition,
		isDragging,
	} = useSortable({ id: member.agent_db_id.toString() });

	const style = {
		transform: CSS.Transform.toString(transform),
		transition,
		opacity: isDragging ? 0.5 : 1,
		zIndex: isDragging ? 1000 : 1,
	};

	return (
		<div
			ref={setNodeRef}
			style={style}
			className={`bg-white dark:bg-gray-800 border ${isDragging ? 'border-primary ring-1 ring-primary shadow-lg scale-[1.02]' : 'border-gray-200 dark:border-gray-700 shadow-sm'
				} rounded-xl p-5 transition-all`}
		>
			<div className="flex justify-between items-center mb-4">
				<div className="flex items-center gap-3">
					{ /* Drag handle */}
					<button
						className="cursor-move p-1.5 text-gray-400 hover:text-gray-600 dark:text-gray-500 dark:hover:text-gray-300 rounded hover:bg-gray-100 dark:hover:bg-gray-700 transition-colors focus:outline-none focus:ring-2 focus:ring-primary/50"
						{...attributes}
						{...listeners}
						title={__('Drag to reorder', 'agentflow-ai')}
						aria-label={__('Drag to reorder', 'agentflow-ai')}
						type="button"
					>
						<svg
							width="16"
							height="16"
							viewBox="0 0 16 16"
							fill="currentColor"
						>
							<path d="M4 4h2v2H4V4zm0 3h2v2H4V7zm0 3h2v2H4v-2zm3-6h2v2H7V4zm0 3h2v2H7V7zm0 3h2v2H7v-2zm3-6h2v2h-2V4zm0 3h2v2h-2V7zm0 3h2v2h-2v-2z" />
						</svg>
					</button>
					<span className="inline-flex items-center justify-center w-6 h-6 rounded-md bg-gray-100 dark:bg-gray-700 text-xs font-semibold text-gray-600 dark:text-gray-300">
						#{index + 1}
					</span>
					<h4 className="font-semibold text-base text-gray-900 dark:text-white m-0">
						{member.agent_name}
					</h4>
				</div>
				<button
					type="button"
					onClick={() => onRemove(index)}
					className="px-3 py-1.5 text-sm font-medium text-red-600 dark:text-red-400 bg-red-50 dark:bg-red-900/20 hover:bg-red-100 dark:hover:bg-red-900/40 rounded-lg transition-colors focus:outline-none focus:ring-2 focus:ring-red-500/50"
				>
					{__('Remove', 'agentflow-ai')}
				</button>
			</div>

			<div className="grid grid-cols-1 md:grid-cols-2 gap-4">
				<div>
					<label className="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">{__('Role', 'agentflow-ai')}</label>
					<select
						className="w-full text-sm rounded-lg border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-700 text-gray-900 dark:text-white focus:ring-primary focus:border-primary px-3 py-2 outline-none transition-colors"
						value={member.role}
						onChange={(e) => onUpdate(index, 'role', e.target.value)}
					>
						{MEMBER_ROLES.map((role) => (
							<option key={role.value} value={role.value}>
								{role.label}
							</option>
						))}
					</select>
				</div>

				{orchestrationMode === 'router' && (
					<div className="md:col-span-2">
						<label className="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">{__('Routing Keywords', 'agentflow-ai')}</label>
						<input
							type="text"
							className="w-full text-sm rounded-lg border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-700 text-gray-900 dark:text-white focus:ring-primary focus:border-primary px-3 py-2 outline-none transition-colors"
							placeholder={__('Comma-separated keywords (e.g., billing, invoice)', 'agentflow-ai')}
							value={member.routing_keywords || ''}
							onChange={(e) => onUpdate(index, 'routing_keywords', e.target.value)}
						/>
						<p className="mt-1 text-xs text-gray-500 dark:text-gray-400">
							{__('Comma-separated keywords (e.g., billing, invoice)', 'agentflow-ai')}
						</p>
					</div>
				)}

				{orchestrationMode === 'supervisor' && member.role !== 'primary' && (
					<div className="md:col-span-2">
						<label className="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">{__('Agent Responsibilities (Supervisor Instructions)', 'agentflow-ai')}</label>
						<textarea
							rows={2}
							className="w-full text-sm rounded-lg border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-700 text-gray-900 dark:text-white focus:ring-primary focus:border-primary px-3 py-2 outline-none transition-colors"
							placeholder={__('Explain to the manager exactly what tasks this agent should handle...', 'agentflow-ai')}
							value={member.routing_keywords || ''}
							onChange={(e) => onUpdate(index, 'routing_keywords', e.target.value)}
						/>
						<p className="mt-1 text-xs text-gray-500 dark:text-gray-400">
							{__('This helps the Manager decide when to delegate tasks to this worker.', 'agentflow-ai')}
						</p>
					</div>
				)}
			</div>
		</div>
	);
}

SortableMemberCard.propTypes = {
	member: PropTypes.object.isRequired,
	index: PropTypes.number.isRequired,
	orchestrationMode: PropTypes.string.isRequired,
	onUpdate: PropTypes.func.isRequired,
	onRemove: PropTypes.func.isRequired,
};

/**
 * Main sortable member list with drag-and-drop context
 * @param root0
 * @param root0.members
 * @param root0.orchestrationMode
 * @param root0.onMembersChange
 * @param root0.onUpdateMember
 * @param root0.onRemoveMember
 */
export default function SortableMemberList({
	members,
	orchestrationMode,
	onMembersChange,
	onUpdateMember,
	onRemoveMember,
}) {
	const sensors = useSensors(
		useSensor(PointerSensor),
		useSensor(KeyboardSensor, {
			coordinateGetter: sortableKeyboardCoordinates,
		})
	);

	const handleDragEnd = (event) => {
		const { active, over } = event;

		if (active.id !== over?.id) {
			const oldIndex = members.findIndex(
				(m) => m.agent_db_id.toString() === active.id
			);
			const newIndex = members.findIndex(
				(m) => m.agent_db_id.toString() === over.id
			);

			const newMembers = arrayMove(members, oldIndex, newIndex).map(
				(member, idx) => ({
					...member,
					execution_order: idx,
				})
			);

			onMembersChange(newMembers);
		}
	};

	if (members.length === 0) {
		return (
			<div className="text-center p-8 bg-gray-50 dark:bg-gray-800/50 rounded-xl border-2 border-dashed border-gray-300 dark:border-gray-700">
				<p className="text-gray-500 dark:text-gray-400 m-0">
					{__(
						'No agents added yet. Add agents to create a team.',
						'agentflow-ai'
					)}
				</p>
			</div>
		);
	}

	return (
		<DndContext
			sensors={sensors}
			collisionDetection={closestCenter}
			onDragEnd={handleDragEnd}
		>
			<SortableContext
				items={members.map((m) => m.agent_db_id.toString())}
				strategy={verticalListSortingStrategy}
			>
				<div className="flex flex-col gap-4">
					{members.map((member, index) => (
						<SortableMemberCard
							key={member.agent_db_id}
							member={member}
							index={index}
							orchestrationMode={orchestrationMode}
							onUpdate={onUpdateMember}
							onRemove={onRemoveMember}
						/>
					))}
				</div>
			</SortableContext>
			{orchestrationMode === 'sequential' && (
				<p className="text-sm text-gray-500 dark:text-gray-400 mt-4 italic flex items-center gap-2">
					<svg className="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" /></svg>
					{__(
						'Drag to reorder. Agents will execute in the order shown above.',
						'agentflow-ai'
					)}
				</p>
			)}
		</DndContext>
	);
}

SortableMemberList.propTypes = {
	members: PropTypes.array.isRequired,
	orchestrationMode: PropTypes.string.isRequired,
	onMembersChange: PropTypes.func.isRequired,
	onUpdateMember: PropTypes.func.isRequired,
	onRemoveMember: PropTypes.func.isRequired,
};
