/**
 * Bulk Actions Toolbar Component
 *
 * Floating toolbar that appears when items are selected.
 * Provides bulk operations like delete, duplicate, export, use in workspace.
 *
 * @version 1.1.0
 * @package
 */
import { __ } from '@wordpress/i18n';
import PropTypes from 'prop-types';
import { ConfirmModal } from './Modal';
import { useState } from '@wordpress/element';
import { Button, IconButton } from '../ui';

/**
 * BulkActionsToolbar Component
 *
 * @param {Object}   props
 * @param {number}   props.selectedCount      - Number of selected items
 * @param {Function} props.onDelete           - Delete handler
 * @param {Function} props.onDuplicate        - Duplicate handler (optional)
 * @param {Function} props.onExport           - Export handler (optional)
 * @param {Function} props.onUseInWorkspace   - Use in Workspace handler (optional)
 * @param {Function} props.onClear            - Clear selection handler
 * @param {string}   props.itemType           - Type of items (e.g., 'agents', 'skills')
 */
export default function BulkActionsToolbar({
	selectedCount,
	onDelete,
	onDuplicate,
	onExport,
	onUseInWorkspace,
	onClear,
	itemType = 'items',
}) {
	const [showDeleteConfirm, setShowDeleteConfirm] = useState(false);

	if (selectedCount === 0) {
		return null;
	}

	const handleDelete = () => {
		setShowDeleteConfirm(true);
	};

	const confirmDelete = () => {
		onDelete();
		setShowDeleteConfirm(false);
	};

	return (
		<>
			<div className="swc-bulk-toolbar swc-animate-in">
				<div className="swc-bulk-toolbar__info">
					<span className="swc-bulk-toolbar__count">
						{selectedCount}
					</span>
					<span className="swc-bulk-toolbar__label">
						{selectedCount === 1
							? __('agent selected', 'agentflow-ai')
							: __('agents selected', 'agentflow-ai')}
					</span>
				</div>

				<div className="swc-bulk-toolbar__actions">
					{onUseInWorkspace && (
						<Button
							variant="primary"
							size="sm"
							onClick={onUseInWorkspace}
							title={__('Use selected agents in Workspace', 'agentflow-ai')}
							icon={<span></span>}
						>
							{__('Use in Workspace', 'agentflow-ai')}
						</Button>
					)}

					{onDuplicate && (
						<Button
							variant="secondary"
							size="sm"
							onClick={onDuplicate}
							title={__('Duplicate selected', 'agentflow-ai')}
							icon={<span></span>}
						>
							{__('Duplicate', 'agentflow-ai')}
						</Button>
					)}

					{onExport && (
						<Button
							variant="secondary"
							size="sm"
							onClick={onExport}
							title={__('Export selected', 'agentflow-ai')}
							icon={<span></span>}
						>
							{__('Export', 'agentflow-ai')}
						</Button>
					)}

					<Button
						variant="danger"
						size="sm"
						onClick={handleDelete}
						title={__('Delete selected', 'agentflow-ai')}
						icon={<span>️</span>}
					>
						{__('Delete', 'agentflow-ai')}
					</Button>

					<IconButton
						className="swc-bulk-toolbar__close"
						onClick={onClear}
						title={__('Clear selection', 'agentflow-ai')}
						aria-label={__('Clear selection', 'agentflow-ai')}
						size="sm"
					>
						
					</IconButton>
				</div>
			</div>

			<ConfirmModal
				isOpen={showDeleteConfirm}
				onClose={() => setShowDeleteConfirm(false)}
				onConfirm={confirmDelete}
				title={__('Delete Selected Items?', 'agentflow-ai')}
				message={`${__('Are you sure you want to delete', 'agentflow-ai')} ${selectedCount} ${itemType}? ${__('This action cannot be undone.', 'agentflow-ai')}`}
				confirmText={__('Delete All', 'agentflow-ai')}
				variant="danger"
			/>
		</>
	);
}

BulkActionsToolbar.propTypes = {
	selectedCount: PropTypes.number.isRequired,
	onDelete: PropTypes.func.isRequired,
	onDuplicate: PropTypes.func,
	onExport: PropTypes.func,
	onUseInWorkspace: PropTypes.func,
	onClear: PropTypes.func,
	itemType: PropTypes.string,
};
