/**
 * GroupManager Component
 *
 * Manages skill groups.
 */
import { useState, useEffect } from '@wordpress/element';
import { __ } from '@wordpress/i18n';
import useSkillsApi from '../../hooks/useSkillsApi';
import GroupList from './GroupList';
import GroupEditor from './GroupEditor';


export default function GroupManager() {
	const {
		groups,
		fetchGroups,
		createGroup,
		updateGroup,
		deleteGroup,
		loading,
	} = useSkillsApi();

	const [isEditing, setIsEditing] = useState(false);
	const [selectedGroup, setSelectedGroup] = useState(null);

	// Initial fetch
	useEffect(() => {
		fetchGroups();
	}, [fetchGroups]);

	const handleCreate = () => {
		setSelectedGroup(null);
		setIsEditing(true);
	};

	const handleEdit = (group) => {
		setSelectedGroup(group);
		setIsEditing(true);
	};

	const handleDelete = async (groupId) => {
		// eslint-disable-next-line no-alert
		if (
			confirm(
				__(
					'Are you sure you want to delete this team? All member agents will be unassigned.',
					'smart-woo-chatbot'
				)
			)
		) {
			await deleteGroup(groupId);
		}
	};

	// Save from GroupForm (which is used inside GroupEditor usually, but here we can use GroupEditor if it replaces GroupForm)
	// But GroupManager currently uses GroupForm. GroupEditor.jsx seems to be a more advanced version or replacing it?
	// GroupEditor.jsx (Step 174) exports GroupEditor. It uses GroupForm internally? No, it has its own form.
	// Step 174 GroupEditor has "Group Settings Card" and "Members Card".
	// GroupForm (Step 150) has basic fields.
	// Users probably want the advanced GroupEditor.
	// So I will use GroupEditor instead of GroupForm.

	const handleSave = async (data) => {
		try {
			if (selectedGroup) {
				await updateGroup(selectedGroup.id, data);
			} else {
				await createGroup(data);
			}
			setIsEditing(false);
			setSelectedGroup(null);
		} catch (error) {
			console.error('Save failed', error);
			// Ideally show error toast
		}
	};

	const handleCancel = () => {
		setIsEditing(false);
		setSelectedGroup(null);
	};

	if (loading && groups.length === 0) {
		return (
			<div className="flex justify-center py-8">
				<div className="w-8 h-8 border-4 border-primary border-t-transparent rounded-full animate-spin"></div>
			</div>
		);
	}

	if (isEditing) {
		return (
			<div className="space-y-6">
				{ /* Use GroupEditor for better UI */}
				<GroupEditor
					group={selectedGroup}
					isNew={!selectedGroup}
					onSave={handleSave}
					onCancel={handleCancel}
				/>
			</div>
		);
	}

	return (
		<div className="space-y-6">
			<div className="flex justify-between items-center mb-6">
				<h3 className="text-xl font-bold text-gray-900 dark:text-white m-0">
					{__('Agent Teams', 'smart-woo-chatbot')}
				</h3>
				<button
					className="inline-flex items-center px-4 py-2 border border-transparent shadow-sm text-sm font-medium rounded-lg text-white bg-primary hover:bg-primary/90 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-primary"
					onClick={handleCreate}
				>
					{__('Create Team', 'smart-woo-chatbot')}
				</button>
			</div>

			<GroupList
				groups={groups}
				onEdit={handleEdit}
				onDelete={handleDelete}
			/>
		</div>
	);
}
