/**
 * InstructionEditor Component
 *
 * Editor for skill instruction sections with add/remove/reorder.
 */
import { __ } from '@wordpress/i18n';
import PropTypes from 'prop-types';
import { Button, IconButton, Select, TextField } from '../ui';

const SECTION_TYPES = [
	{ id: 'bullets', label: 'Bullet Points', icon: '•' },
	{ id: 'numbered', label: 'Numbered List', icon: '1.' },
	{ id: 'paragraph', label: 'Paragraph', icon: '¶' },
];

// Generate unique ID
const generateId = () =>
	`section-${Date.now()}-${Math.random().toString(36).substr(2, 9)}`;

export default function InstructionEditor({ sections, onChange }) {
	// Add new section
	const addSection = () => {
		const newSection = {
			id: generateId(),
			heading: '',
			type: 'bullets',
			items: [''],
			content: '',
		};
		onChange([...sections, newSection]);
	};

	// Update section
	const updateSection = (id, updates) => {
		onChange(
			sections.map((s) => (s.id === id ? { ...s, ...updates } : s))
		);
	};

	// Remove section
	const removeSection = (id) => {
		if (sections.length <= 1) {
			return; // Keep at least one section
		}
		onChange(sections.filter((s) => s.id !== id));
	};

	// Move section
	const moveSection = (id, direction) => {
		const index = sections.findIndex((s) => s.id === id);
		if (
			(direction === 'up' && index === 0) ||
			(direction === 'down' && index === sections.length - 1)
		) {
			return;
		}

		const newSections = [...sections];
		const newIndex = direction === 'up' ? index - 1 : index + 1;
		[newSections[index], newSections[newIndex]] = [
			newSections[newIndex],
			newSections[index],
		];
		onChange(newSections);
	};

	// Add item to list
	const addItem = (sectionId) => {
		const section = sections.find((s) => s.id === sectionId);
		if (section) {
			updateSection(sectionId, { items: [...section.items, ''] });
		}
	};

	// Update item
	const updateItem = (sectionId, itemIndex, value) => {
		const section = sections.find((s) => s.id === sectionId);
		if (section) {
			const newItems = [...section.items];
			newItems[itemIndex] = value;
			updateSection(sectionId, { items: newItems });
		}
	};

	// Remove item
	const removeItem = (sectionId, itemIndex) => {
		const section = sections.find((s) => s.id === sectionId);
		if (section && section.items.length > 1) {
			const newItems = section.items.filter(
				(_, i) => i !== itemIndex
			);
			updateSection(sectionId, { items: newItems });
		}
	};

	return (
		<div className="space-y-4">
			{sections.map((section, index) => (
				<div key={section.id} className="bg-gray-50 dark:bg-gray-800/50 rounded-xl border border-gray-200 dark:border-gray-700 overflow-hidden shadow-sm">
					{ /* Section header */}
					<div className="flex flex-col sm:flex-row sm:items-center justify-between p-4 bg-white dark:bg-gray-800 border-b border-gray-200 dark:border-gray-700 gap-4">
						<div className="flex-1">
							<TextField
								className="w-full font-medium"
								value={section.heading}
								onChange={(value) => updateSection(section.id, { heading: value })}
								placeholder={__('Section heading (e.g., "When to use this skill")', 'agentflow-ai')}
							/>
						</div>

						<div className="flex items-center gap-2">
							{ /* Type selector */}
							<Select
								className="w-40"
								value={section.type}
								options={SECTION_TYPES.map((type) => ({
									value: type.id,
									label: `${type.icon} ${type.label}`,
								}))}
								onChange={(value) => updateSection(section.id, { type: value })}
							/>

							<div className="flex items-center border border-gray-200 dark:border-gray-700 rounded-md bg-gray-50 dark:bg-gray-900">
								{ /* Move buttons */}
								<IconButton
									variant="ghost"
									onClick={() => moveSection(section.id, 'up')}
									disabled={index === 0}
									title={__('Move up', 'agentflow-ai')}
									aria-label={__('Move section up', 'agentflow-ai')}
								>
									↑
								</IconButton>
								<div className="w-px h-4 bg-gray-200 dark:bg-gray-700"></div>
								<IconButton
									variant="ghost"
									onClick={() => moveSection(section.id, 'down')}
									disabled={index === sections.length - 1}
									title={__('Move down', 'agentflow-ai')}
									aria-label={__('Move section down', 'agentflow-ai')}
								>
									↓
								</IconButton>
							</div>

							{ /* Delete button */}
							<IconButton
								variant="ghost"
								isDestructive
								onClick={() => removeSection(section.id)}
								disabled={sections.length <= 1}
								title={__('Delete section', 'agentflow-ai')}
								aria-label={__('Delete section', 'agentflow-ai')}
							>
								<svg className="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" /></svg>
							</IconButton>
						</div>
					</div>

					{ /* Section content */}
					<div className="p-4">
						{section.type === 'paragraph' ? (
							<TextField
								value={section.content}
								onChange={(value) => updateSection(section.id, { content: value })}
								placeholder={__('Enter paragraph content…', 'agentflow-ai')}
								rows={4}
								multiline
							/>
						) : (
							<div className="space-y-3">
								{section.items.map((item, itemIndex) => (
									<div key={itemIndex} className="flex items-start gap-3">
										<span className="mt-2 text-gray-500 dark:text-gray-400 font-medium min-w-[1.5rem] text-right">
											{section.type === 'bullets' ? '•' : `${itemIndex + 1}.`}
										</span>
										<div className="flex-1">
											<TextField
												value={item}
												onChange={(value) => updateItem(section.id, itemIndex, value)}
												placeholder={__('Enter item…', 'agentflow-ai')}
											/>
										</div>
										<IconButton
											variant="ghost"
											isDestructive
											onClick={() => removeItem(section.id, itemIndex)}
											disabled={section.items.length <= 1}
											title={__('Remove item', 'agentflow-ai')}
											aria-label={__('Remove item', 'agentflow-ai')}
										>
											×
										</IconButton>
									</div>
								))}

								<div className="pt-2 pl-9">
									<Button variant="ghost" size="sm" onClick={() => addItem(section.id)}>
										+ {__('Add Item', 'agentflow-ai')}
									</Button>
								</div>
							</div>
						)}
					</div>
				</div>
			))}

			{ /* Add section button */}
			<div className="pt-2">
				<Button variant="secondary" onClick={addSection}>
					+ {__('Add Section', 'agentflow-ai')}
				</Button>
			</div>
		</div>
	);
}

InstructionEditor.propTypes = {
	sections: PropTypes.array.isRequired,
	onChange: PropTypes.func.isRequired,
};
