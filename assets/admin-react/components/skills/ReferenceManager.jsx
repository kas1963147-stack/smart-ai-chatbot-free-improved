/**
 * ReferenceManager Component
 *
 * Manage reference documents for a skill.
 */
import { useState } from '@wordpress/element';
import { __ } from '@wordpress/i18n';
import PropTypes from 'prop-types';

// Generate unique ID
const generateId = () =>
	`ref-${Date.now()}-${Math.random().toString(36).substr(2, 9)}`;

// Generate filename from title
const generateFilename = (title) => {
	return (
		title
			.toLowerCase()
			.replace(/[^a-z0-9\s-]/g, '')
			.replace(/[\s_]+/g, '-')
			.replace(/-+/g, '-')
			.trim() + '.md'
	);
};

export default function ReferenceManager({ references, onChange }) {
	const [editingId, setEditingId] = useState(null);
	const [editData, setEditData] = useState({ title: '', content: '' });

	// Add new reference
	const addReference = () => {
		const newRef = {
			id: generateId(),
			name: '',
			title: '',
			content: '',
			isNew: true,
		};
		setEditingId(newRef.id);
		setEditData({ title: '', content: '' });
		onChange([...references, newRef]);
	};

	// Start editing
	const startEdit = (ref) => {
		setEditingId(ref.id);
		setEditData({ title: ref.title, content: ref.content });
	};

	// Save edit
	const saveEdit = (id) => {
		const filename = generateFilename(editData.title);
		onChange(
			references.map((r) =>
				r.id === id
					? {
						...r,
						name: filename,
						title: editData.title,
						content: editData.content,
						isNew: false,
					}
					: r
			)
		);
		setEditingId(null);
	};

	// Cancel edit
	const cancelEdit = (id) => {
		const ref = references.find((r) => r.id === id);
		if (ref && ref.isNew && !ref.name) {
			// Remove if it was a new empty reference
			onChange(references.filter((r) => r.id !== id));
		}
		setEditingId(null);
	};

	// Delete reference
	const deleteReference = (id) => {
		if (
			!confirm(
				__(
					'Are you sure you want to delete this reference document?',
					'smart-woo-chatbot'
				)
			)
		) {
			return;
		}
		onChange(references.filter((r) => r.id !== id));
	};

	return (
		<div className="space-y-4">
			{references.length === 0 && editingId === null ? (
				<div className="border border-dashed border-gray-300 dark:border-gray-700 rounded-xl p-8 text-center bg-gray-50 dark:bg-gray-800/50">
					<p className="text-gray-500 dark:text-gray-400">
						{__('Reference documents contain detailed policies, procedures, or information that the AI can load when needed.', 'smart-woo-chatbot')}
					</p>
				</div>
			) : (
				<div className="grid grid-cols-1 gap-4">
					{references.map((ref) => (
						<div key={ref.id} className="bg-white dark:bg-gray-800 rounded-xl border border-gray-200 dark:border-gray-700 shadow-sm overflow-hidden">
							{editingId === ref.id ? (
								// Edit mode
								<div className="p-4 space-y-4 bg-gray-50 dark:bg-gray-800/50">
									<div>
										<label className="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">
											{__('Document Title', 'smart-woo-chatbot')}
										</label>
										<input
											type="text"
											className="block w-full rounded-lg border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-700 text-gray-900 dark:text-white shadow-sm focus:border-primary focus:ring-primary sm:text-sm"
											value={editData.title}
											onChange={(e) => setEditData({ ...editData, title: e.target.value })}
											placeholder={__('e.g., Refund Policy', 'smart-woo-chatbot')}
										/>
										{editData.title && (
											<p className="mt-2 text-sm text-gray-500 dark:text-gray-400">
												{__('Filename:', 'smart-woo-chatbot')}{' '}
												<span className="font-mono bg-gray-100 dark:bg-gray-700 px-1 py-0.5 rounded text-xs">
													{generateFilename(editData.title)}
												</span>
											</p>
										)}
									</div>

									<div>
										<label className="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">
											{__('Content', 'smart-woo-chatbot')}
										</label>
										<textarea
											className="block w-full rounded-lg border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-700 text-gray-900 dark:text-white shadow-sm focus:border-primary focus:ring-primary sm:text-sm font-mono"
											value={editData.content}
											onChange={(e) => setEditData({ ...editData, content: e.target.value })}
											placeholder={__('Enter document content…', 'smart-woo-chatbot')}
											rows={8}
										/>
									</div>

									<div className="flex items-center justify-end gap-2 pt-2">
										<button
											type="button"
											className="inline-flex items-center px-3 py-1.5 border border-gray-300 dark:border-gray-600 shadow-sm text-xs font-medium rounded text-gray-700 dark:text-gray-200 bg-white dark:bg-gray-700 hover:bg-gray-50 dark:hover:bg-gray-600 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-primary"
											onClick={() => cancelEdit(ref.id)}
										>
											{__('Cancel', 'smart-woo-chatbot')}
										</button>
										<button
											type="button"
											className="inline-flex items-center px-3 py-1.5 border border-transparent shadow-sm text-xs font-medium rounded text-white bg-primary hover:bg-primary/90 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-primary disabled:opacity-50 disabled:cursor-not-allowed"
											onClick={() => saveEdit(ref.id)}
											disabled={!editData.title.trim()}
										>
											{__('Save', 'smart-woo-chatbot')}
										</button>
									</div>
								</div>
							) : (
								// Display mode
								<div className="p-4 flex flex-col sm:flex-row sm:items-center justify-between gap-4">
									<div className="flex items-start gap-3">
										<div className="mt-1 flex-shrink-0 w-8 h-8 flex items-center justify-center rounded-lg bg-blue-50 dark:bg-blue-900/30 text-blue-600 dark:text-blue-400">
											<svg className="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
												<path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
											</svg>
										</div>
										<div>
											<div className="text-sm font-medium text-gray-900 dark:text-white">
												{ref.title || ref.name}
											</div>
											<div className="text-xs text-gray-500 dark:text-gray-400 mt-1 flex items-center gap-2">
												<span className="font-mono bg-gray-100 dark:bg-gray-800 px-1.5 py-0.5 rounded">
													{ref.name}
												</span>
												<span>•</span>
												<span>
													{ref.content?.length || 0}{' '}
													{__('chars', 'smart-woo-chatbot')}
												</span>
											</div>
										</div>
									</div>

									<div className="flex items-center gap-2 self-end sm:self-auto">
										<button
											type="button"
											className="inline-flex items-center px-3 py-1.5 border border-gray-300 dark:border-gray-600 shadow-sm text-xs font-medium rounded text-gray-700 dark:text-gray-200 bg-white dark:bg-gray-700 hover:bg-gray-50 dark:hover:bg-gray-600 focus:outline-none"
											onClick={() => startEdit(ref)}
										>
											{__('Edit', 'smart-woo-chatbot')}
										</button>
										<button
											type="button"
											className="inline-flex items-center px-3 py-1.5 border border-red-200 dark:border-red-800 shadow-sm text-xs font-medium rounded text-red-700 dark:text-red-400 bg-red-50 dark:bg-red-900/20 hover:bg-red-100 dark:hover:bg-red-900/40 focus:outline-none"
											onClick={() => deleteReference(ref.id)}
										>
											{__('Delete', 'smart-woo-chatbot')}
										</button>
									</div>
								</div>
							)}
						</div>
					))}
				</div>
			)}

			{ /* Add button */}
			<div className="pt-2">
				<button
					type="button"
					className="inline-flex items-center px-4 py-2 border border-gray-300 dark:border-gray-600 shadow-sm text-sm font-medium rounded-lg text-gray-700 dark:text-gray-200 bg-white dark:bg-gray-700 hover:bg-gray-50 dark:hover:bg-gray-600 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-primary disabled:opacity-50 disabled:cursor-not-allowed"
					onClick={addReference}
					disabled={editingId !== null}
				>
					+ {__('Add Reference Document', 'smart-woo-chatbot')}
				</button>
			</div>
		</div>
	);
}

ReferenceManager.propTypes = {
	references: PropTypes.array.isRequired,
	onChange: PropTypes.func.isRequired,
};
