/**
 * LocationAssigner Component
 *
 * Assign agent OR group to page locations.
 * Premium Design System V2 styling.
 */
import { useState, useEffect, useCallback } from '@wordpress/element';
import { __ } from '@wordpress/i18n';
import PropTypes from 'prop-types';
import useAgentApi from '../../hooks/useAgentApi';
import { Select, TextField } from '../ui';

const LOCATION_TYPES = [
	{ value: 'global', label: 'All Pages (Global)' },
	{ value: 'page_id', label: 'Specific Page' },
	{ value: 'post_type', label: 'Post Type' },
	{ value: 'url_pattern', label: 'URL Pattern' },
	{ value: 'cart', label: 'Cart Page' },
	{ value: 'checkout', label: 'Checkout Page' },
	{ value: 'account', label: 'My Account' },
];

export default function LocationAssigner({ agentId, groupId, isNew }) {
	const [assignments, setAssignments] = useState([]);
	const [loading, setLoading] = useState(false);
	const [newAssignment, setNewAssignment] = useState({
		location_type: 'global',
		location_value: '',
		priority: 0,
	});

	const {
		getAssignments,
		createAssignment,
		deleteAssignment,
		getGroupAssignments,
		createGroupAssignment,
	} = useAgentApi();

	const isGroup = !!groupId;
	const entityId = isGroup ? groupId : agentId;

	const loadAssignments = useCallback(async () => {
		setLoading(true);
		try {
			const response = isGroup
				? await getGroupAssignments(entityId)
				: await getAssignments(entityId);
			setAssignments(response.assignments || []);
		} catch (err) {
			console.error('Failed to load assignments:', err);
		} finally {
			setLoading(false);
		}
	}, [entityId, getAssignments, getGroupAssignments, isGroup]);

	// Fetch existing assignments
	useEffect(() => {
		if (entityId && !isNew) {
			loadAssignments();
		}
	}, [entityId, isNew, loadAssignments]);

	// Add new assignment
	const handleAdd = async () => {
		if (isNew) {
			// For new agents/groups, just add to local state
			setAssignments((prev) => [
				...prev,
				{
					...newAssignment,
					id: 'temp_' + Date.now(),
					isLocal: true,
				},
			]);
		} else {
			// For existing, save to API
			setLoading(true);
			try {
				if (isGroup) {
					await createGroupAssignment(entityId, newAssignment);
				} else {
					await createAssignment(entityId, newAssignment);
				}
				await loadAssignments();
			} catch (err) {
				console.error('Failed to add assignment:', err);
			} finally {
				setLoading(false);
			}
		}

		// Reset form
		setNewAssignment({
			location_type: 'global',
			location_value: '',
			priority: 0,
		});
	};

	// Remove assignment
	const handleRemove = async (assignment) => {
		if (assignment.isLocal) {
			setAssignments((prev) =>
				prev.filter((a) => a.id !== assignment.id)
			);
		} else {
			setLoading(true);
			try {
				await deleteAssignment(assignment.id);
				await loadAssignments();
			} catch (err) {
				console.error('Failed to delete assignment:', err);
			} finally {
				setLoading(false);
			}
		}
	};

	// Check if location type needs a value
	const needsValue = (type) => {
		return ['page_id', 'post_type', 'url_pattern', 'taxonomy'].includes(
			type
		);
	};

	// Get placeholder for value input
	const getValuePlaceholder = (type) => {
		switch (type) {
			case 'page_id':
				return __('Enter page ID', 'agentflow-ai');
			case 'post_type':
				return __('e.g., product, post', 'agentflow-ai');
			case 'url_pattern':
				return __('e.g., /contact*, /shop/*', 'agentflow-ai');
			case 'taxonomy':
				return __('e.g., category:123', 'agentflow-ai');
			default:
				return '';
		}
	};

	if (isNew) {
		return (
			<div className="bg-white/80 dark:bg-gray-800/80 backdrop-blur-md border border-gray-200 dark:border-gray-700 rounded-xl p-6 shadow-sm">
				<div className="pb-4 mb-4 border-b border-gray-100 dark:border-gray-700">
					<h3 className="text-lg font-semibold text-gray-900 dark:text-white m-0">
						{__('Page Locations', 'agentflow-ai')}
					</h3>
				</div>
				<div>
					<p className="text-sm text-gray-500 dark:text-gray-400 m-0">
						{__(
							'Save the agent or group first, then assign it to page locations.',
							'agentflow-ai'
						)}
					</p>
				</div>
			</div>
		);
	}

	return (
		<div className="space-y-6">
			<div className="bg-white/80 dark:bg-gray-800/80 backdrop-blur-md border border-gray-200 dark:border-gray-700 rounded-xl p-6 shadow-sm">
				{ /* Header */}
				<div className="flex justify-between items-start pb-4 mb-4 border-b border-gray-100 dark:border-gray-700">
					<div>
						<h3 className="text-lg font-semibold text-gray-900 dark:text-white m-0">
							{__('Page Locations', 'agentflow-ai')}
						</h3>
						<p className="text-sm text-gray-500 dark:text-gray-400 mt-1">
							{isGroup
								? __(
									'Choose where this agent group should appear.',
									'agentflow-ai'
								)
								: __(
									'Choose where this agent should appear.',
									'agentflow-ai'
								)}
						</p>
					</div>
				</div>

				{ /* Body */}
				<div>
					{ /* Loading */}
					{loading && (
						<div className="flex justify-center py-4">
							<div className="w-6 h-6 border-2 border-primary border-t-transparent rounded-full animate-spin"></div>
						</div>
					)}

					{ /* Empty State */}
					{!loading && assignments.length === 0 && (
						<p className="text-sm text-gray-500 dark:text-gray-400 mb-6">
							{__(
								'No locations assigned yet.',
								'agentflow-ai'
							)}
						</p>
					)}

					{ /* Assignments List */}
					{assignments.length > 0 && (
						<div className="flex flex-col gap-3 mb-6">
							{assignments.map((assignment) => (
								<div
									key={assignment.id}
									className="flex justify-between items-center p-4 bg-white dark:bg-gray-800 border border-gray-200 dark:border-gray-700 rounded-lg transition-all"
								>
									<div className="flex items-center gap-2 flex-wrap">
										<span className="inline-flex items-center px-2.5 py-1 rounded-full text-xs font-medium bg-blue-100 dark:bg-blue-900/40 text-blue-700 dark:text-blue-300">
											{LOCATION_TYPES.find(
												(t) =>
													t.value ===
													assignment.location_type
											)?.label ||
												assignment.location_type}
										</span>
										{assignment.location_value && (
											<span className="text-sm text-gray-600 dark:text-gray-300">
												: {assignment.location_value}
											</span>
										)}
										<span className="inline-flex items-center px-2 py-1 rounded-full text-[10px] font-medium bg-gray-100 dark:bg-gray-700 text-gray-600 dark:text-gray-300">
											{__(
												'Priority',
												'agentflow-ai'
											)}
											: {assignment.priority}
										</span>
									</div>
									<button
										type="button"
										className="px-3 py-1.5 text-sm font-medium text-red-600 dark:text-red-400 bg-transparent rounded-md transition-colors hover:bg-red-50 dark:hover:bg-red-900/30 focus:outline-none"
										onClick={() =>
											handleRemove(assignment)
										}
									>
										{__('Remove', 'agentflow-ai')}
									</button>
								</div>
							))}
						</div>
					)}

					{ /* Add New Assignment Section */}
					<div className="p-5 bg-gray-50 dark:bg-gray-800/50 rounded-lg border border-gray-100 dark:border-gray-700 mt-6">
						<h4 className="m-0 mb-4 text-base font-semibold text-gray-800 dark:text-gray-200">
							{__('Add Location', 'agentflow-ai')}
						</h4>

						<div className="flex flex-col gap-4">
							<Select
								label={__(
									'Location Type',
									'agentflow-ai'
								)}
								value={newAssignment.location_type}
								options={LOCATION_TYPES}
								onChange={(val) =>
									setNewAssignment((prev) => ({
										...prev,
										location_type: val,
										location_value: '',
									}))
								}
							/>

							{needsValue(newAssignment.location_type) && (
								<TextField
									label={__('Value', 'agentflow-ai')}
									value={newAssignment.location_value}
									onChange={(val) =>
										setNewAssignment((prev) => ({
											...prev,
											location_value: val,
										}))
									}
									placeholder={getValuePlaceholder(
										newAssignment.location_type
									)}
								/>
							)}

							<TextField
								label={__('Priority', 'agentflow-ai')}
								help={__(
									'Higher priority wins when multiple agents match',
									'agentflow-ai'
								)}
								type="number"
								value={newAssignment.priority}
								onChange={(val) =>
									setNewAssignment((prev) => ({
										...prev,
										priority: parseInt(val) || 0,
									}))
								}
							/>

							<button
								type="button"
								onClick={handleAdd}
								disabled={loading}
								className="mt-2 self-start inline-flex items-center px-4 py-2 border border-gray-300 dark:border-gray-600 shadow-sm text-sm font-medium rounded-md text-gray-700 dark:text-gray-200 bg-white dark:bg-gray-700 hover:bg-gray-50 dark:hover:bg-gray-600 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-primary transition-colors disabled:opacity-50 disabled:cursor-not-allowed"
							>
								{__('Add Location', 'agentflow-ai')}
							</button>
						</div>
					</div>
				</div>
			</div>
		</div>
	);
}

LocationAssigner.propTypes = {
	agentId: PropTypes.oneOfType([PropTypes.string, PropTypes.number]),
	groupId: PropTypes.oneOfType([PropTypes.string, PropTypes.number]),
	isNew: PropTypes.bool,
};
