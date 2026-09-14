/**
 * KnowledgeEditor Component
 *
 * Form to create/edit knowledge documents.
 * Supports manual Markdown, URL scraping, and PDF to Text conversion.
 */
import { useEffect, useState } from '@wordpress/element';
import { __ } from '@wordpress/i18n';
import PropTypes from 'prop-types';
import { FileText, Link as LinkIcon, Upload, Type, AlertCircle } from 'lucide-react';

const CATEGORIES = [
    { value: 'policies', label: __('Policies', 'agentflow-ai') },
    { value: 'products', label: __('Products', 'agentflow-ai') },
    { value: 'company_info', label: __('Company Info', 'agentflow-ai') },
    { value: 'general', label: __('General', 'agentflow-ai') },
];

export default function KnowledgeEditor({ item, onSave, onCancel }) {
    // Map backend type values to editor tab IDs
    const mapTypeToTab = (type) => {
        if (type === 'url') return 'url';
        if (type === 'pdf') return 'pdf';
        return 'markdown'; // 'manual', 'markdown', or anything else
    };
    const initialType = item ? mapTypeToTab(item.type) : 'markdown';
    const [inputType, setInputType] = useState(initialType);
    
    const [formData, setFormData] = useState({
        id: null,
        title: '',
        description: '',
        category: 'general',
        content: '',
        is_active: true,
        type: 'markdown',
        sourceUrl: '',
    });
    
    // For file uploads
    const [file, setFile] = useState(null);
    const [isSaving, setIsSaving] = useState(false);
    const [errors, setErrors] = useState({});

    useEffect(() => {
        if (item) {
            setFormData({
                id: item.id || null,
                title: item.title || '',
                description: item.description || '',
                category: item.category || 'general',
                content: item.content || '',
                is_active: item.is_active !== undefined ? !!item.is_active : true,
                type: item.type || 'markdown',
                sourceUrl: item.source_url || '',
            });
            setInputType(mapTypeToTab(item.type)); // Restore the correct tab based on actual document type
        }
    }, [item]);

    // When clicking a tab, update both UI state and underlying form type
    const handleTypeChange = (type) => {
        setInputType(type);
        setFormData(prev => ({ ...prev, type }));
    };

    const handleChange = (field, value) => {
        setFormData((prev) => ({ ...prev, [field]: value }));
        setErrors((prev) => ({ ...prev, [field]: null }));
    };

    const handleFileChange = (e) => {
        const selected = e.target.files[0];
        if (selected && selected.type === 'application/pdf') {
            setFile(selected);
            setErrors(prev => ({ ...prev, file: null }));
        } else {
            setFile(null);
            setErrors(prev => ({ ...prev, file: __('Please select a valid PDF file.', 'agentflow-ai') }));
        }
    };

    const validate = () => {
        const nextErrors = {};
        if (!formData.title.trim()) {
            nextErrors.title = __('Title is required', 'agentflow-ai');
        }
        
        if (inputType === 'markdown' && !formData.content.trim()) {
            nextErrors.content = __('Content is required', 'agentflow-ai');
        }
        
        if (inputType === 'url' && !formData.sourceUrl.trim()) {
            nextErrors.sourceUrl = __('URL is required', 'agentflow-ai');
        } else if (inputType === 'url') {
            try {
                new URL(formData.sourceUrl);
            } catch (e) {
                nextErrors.sourceUrl = __('Please enter a valid URL (including http/https)', 'agentflow-ai');
            }
        }
        
        if (inputType === 'pdf' && !file && !item) {
            nextErrors.file = __('Please select a PDF file to upload', 'agentflow-ai');
        }

        setErrors(nextErrors);
        return Object.keys(nextErrors).length === 0;
    };

    const handleSubmit = async (event) => {
        event.preventDefault();
        if (!validate()) return;
        
        setIsSaving(true);
        
        try {
            // For markdown and url, send standard JSON
            // Map camelCase form keys to snake_case API keys
            if (inputType === 'markdown' || inputType === 'url') {
                const apiPayload = {
                    id: formData.id,
                    title: formData.title,
                    description: formData.description,
                    category: formData.category,
                    content: formData.content,
                    is_active: formData.is_active,
                    type: inputType === 'url' ? 'url' : 'markdown',
                    source_url: formData.sourceUrl || '',
                };
                await onSave(apiPayload);
            } 
            // For PDF, we must use FormData to send the file
            else if (inputType === 'pdf') {
                if (file) {
                    const submitData = new FormData();
                    submitData.append('title', formData.title);
                    submitData.append('description', formData.description);
                    submitData.append('category', formData.category);
                    submitData.append('type', 'pdf');
                    submitData.append('is_active', formData.is_active ? '1' : '0');
                    if (formData.id) submitData.append('id', formData.id);
                    submitData.append('file', file);
                    
                    await onSave(submitData);
                } else {
                    // Updating an existing document that was originally a PDF (just changing title/desc)
                    await onSave({
                        ...formData,
                        type: 'markdown' // Fallback to markdown if no new file is provided during edit
                    });
                }
            }
        } catch (err) {
            // Error is handled by parent, reset saving state
            setIsSaving(false);
        }
    };

    const inputBase =
        'w-full px-4 py-2.5 text-sm rounded-xl border bg-gray-50 dark:bg-slate-900/50 text-gray-900 dark:text-white placeholder-gray-400 dark:placeholder-slate-500 transition-all duration-200 focus:outline-none focus:bg-white dark:focus:bg-slate-800 focus:ring-2 focus:ring-primary/20 focus:border-primary';
    const inputError = 'border-red-300 bg-red-50 dark:border-red-500/50 dark:bg-red-900/10 focus:border-red-500 focus:ring-red-500/20';
    const inputNormal = 'border-gray-200 dark:border-slate-700 hover:border-gray-300 dark:hover:border-slate-600 hover:bg-white dark:hover:bg-slate-800';

    return (
        <form onSubmit={handleSubmit} className="bg-white dark:bg-slate-800 rounded-2xl shadow-xl border border-gray-200 dark:border-slate-700 overflow-hidden flex flex-col max-h-[90vh]">
            
            {/* Header */}
            <div className="px-6 py-4 border-b border-gray-100 dark:border-slate-700 bg-gray-50 dark:bg-slate-800/50 flex items-center justify-between shrink-0">
                <div className="flex items-center gap-3">
                    <div className="w-10 h-10 rounded-xl bg-primary/10 flex items-center justify-center">
                        <FileText className="w-5 h-5 text-primary" />
                    </div>
                    <div>
                        <h2 className="text-lg font-semibold text-gray-900 dark:text-white">
                            {item ? __('Edit Document', 'agentflow-ai') : __('Create Document', 'agentflow-ai')}
                        </h2>
                        <p className="text-sm text-gray-500 dark:text-slate-400">
                            {item ? __('Update document information', 'agentflow-ai') : __('Add knowledge for an AI agent', 'agentflow-ai')}
                        </p>
                    </div>
                </div>
                <button
                    type="button"
                    onClick={onCancel}
                    className="p-2 text-gray-400 hover:text-gray-600 dark:hover:text-slate-300 hover:bg-gray-100 dark:hover:bg-slate-700 rounded-lg transition-colors"
                >
                    <svg className="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M6 18L18 6M6 6l12 12" />
                    </svg>
                </button>
            </div>

            {/* Content Area (Scrollable) */}
            <div className="p-6 overflow-y-auto custom-scrollbar">
                
                {/* Method Selection Tabs (show for both create and edit) */}
                {(
                    <div className="mb-6">
                        <label className="block text-sm font-medium text-gray-700 dark:text-slate-300 mb-2">
                            {__('Source Method', 'agentflow-ai')}
                        </label>
                        <div className="grid grid-cols-3 gap-3 p-1 bg-gray-100 dark:bg-slate-900/50 rounded-xl border border-gray-200 dark:border-slate-700/50">
                            {[
                                { id: 'markdown', label: __('Write Text', 'agentflow-ai'), icon: Type },
                                { id: 'url', label: __('Web Page', 'agentflow-ai'), icon: LinkIcon },
                                { id: 'pdf', label: __('Upload PDF', 'agentflow-ai'), icon: Upload },
                            ].map((tab) => (
                                <button
                                    key={tab.id}
                                    type="button"
                                    onClick={() => handleTypeChange(tab.id)}
                                    className={`flex items-center justify-center gap-2 px-3 py-2 text-sm font-medium rounded-lg transition-all duration-200 ${
                                        inputType === tab.id
                                            ? 'bg-white dark:bg-slate-800 text-primary shadow-sm ring-1 ring-gray-200 dark:ring-slate-700'
                                            : 'text-gray-500 dark:text-slate-400 hover:text-gray-700 dark:hover:text-slate-300 hover:bg-gray-50 dark:hover:bg-slate-800/50'
                                    }`}
                                >
                                    <tab.icon className="w-4 h-4" />
                                    {tab.label}
                                </button>
                            ))}
                        </div>
                    </div>
                )}

                <div className="space-y-5">
                    {/* Title */}
                    <div>
                        <label className="block text-sm font-medium text-gray-700 dark:text-slate-300 mb-1.5">
                            {__('Document Title', 'agentflow-ai')} <span className="text-red-500">*</span>
                        </label>
                        <input
                            type="text"
                            value={formData.title}
                            onChange={(e) => handleChange('title', e.target.value)}
                            className={`${inputBase} ${errors.title ? inputError : inputNormal}`}
                            placeholder={__('e.g. 2024 Refund Policy', 'agentflow-ai')}
                            autoFocus
                        />
                        {errors.title && (
                            <p className="flex items-center gap-1.5 mt-2 text-xs text-red-600 dark:text-red-400">
                                <AlertCircle className="w-3.5 h-3.5" />
                                {errors.title}
                            </p>
                        )}
                    </div>

                    {/* Dynamic Source Input based on Type */}
                    <div className="p-4 bg-gray-50/50 dark:bg-slate-900/30 rounded-xl border border-gray-100 dark:border-slate-700/50 space-y-4">
                        
                        {/* URL Input */}
                        {inputType === 'url' && (
                            <div>
                                <label className="block text-sm font-medium text-gray-700 dark:text-slate-300 mb-1.5 flex justify-between">
                                    <span>{__('Web Page URL', 'agentflow-ai')} <span className="text-red-500">*</span></span>
                                    <span className="text-xs text-primary font-normal">{__('Will be converted to text', 'agentflow-ai')}</span>
                                </label>
                                <div className="relative">
                                    <input
                                        type="url"
                                        value={formData.sourceUrl}
                                        onChange={(e) => handleChange('sourceUrl', e.target.value)}
                                        className={`${inputBase} ${errors.sourceUrl ? inputError : inputNormal}`}
                                        placeholder="https://example.com/policy"
                                    />
                                </div>
                                {errors.sourceUrl && (
                                    <p className="flex items-center gap-1.5 mt-2 text-xs text-red-600 dark:text-red-400">
                                        <AlertCircle className="w-3.5 h-3.5" />
                                        {errors.sourceUrl}
                                    </p>
                                )}
                            </div>
                        )}

                        {/* PDF Upload */}
                        {inputType === 'pdf' && (
                            <div>
                                <label className="block text-sm font-medium text-gray-700 dark:text-slate-300 mb-1.5 flex justify-between">
                                    <span>{__('PDF File', 'agentflow-ai')} <span className="text-red-500">*</span></span>
                                    <span className="text-xs text-primary font-normal">{__('Will be converted to text', 'agentflow-ai')}</span>
                                </label>
                                <div className={`flex justify-center px-6 pt-5 pb-6 border-2 border-dashed rounded-xl ${errors.file ? 'border-red-300 bg-red-50 dark:bg-red-900/10' : 'border-gray-300 dark:border-slate-600 hover:border-primary dark:hover:border-primary bg-white dark:bg-slate-800 transition-colors'}`}>
                                    <div className="space-y-1 text-center">
                                        <div className="mx-auto h-12 w-12 text-gray-400 bg-gray-50 dark:bg-slate-900 rounded-xl flex flex-col items-center justify-center">
                                            <Upload className="mx-auto h-6 w-6" />
                                        </div>
                                        <div className="flex text-sm text-gray-600 dark:text-slate-400 justify-center">
                                            <label htmlFor="file-upload" className="relative cursor-pointer bg-white dark:bg-slate-800 rounded-md font-medium text-primary hover:text-primary/80 focus-within:outline-none">
                                                <span>{file ? file.name : __('Upload a PDF file', 'agentflow-ai')}</span>
                                                <input id="file-upload" name="file-upload" type="file" className="sr-only" accept=".pdf" onChange={handleFileChange} />
                                            </label>
                                            {!file && <p className="pl-1">{__('or drag and drop', 'agentflow-ai')}</p>}
                                        </div>
                                        <p className="text-xs text-gray-500 dark:text-slate-500">
                                            {__('PDF up to 10MB', 'agentflow-ai')}
                                        </p>
                                    </div>
                                </div>
                                {errors.file && (
                                    <p className="flex items-center gap-1.5 mt-2 text-xs text-red-600 dark:text-red-400">
                                        <AlertCircle className="w-3.5 h-3.5" />
                                        {errors.file}
                                    </p>
                                )}
                            </div>
                        )}

                        {/* Markdown Text Area */}
                        {inputType === 'markdown' && (
                            <div>
                                <label className="block text-sm font-medium text-gray-700 dark:text-slate-300 mb-1.5 flex justify-between">
                                    <span>{__('Content', 'agentflow-ai')} <span className="text-red-500">*</span></span>
                                    <span className="text-xs text-gray-400 font-normal">{__('Markdown supported', 'agentflow-ai')}</span>
                                </label>
                                <textarea
                                    value={formData.content}
                                    onChange={(e) => handleChange('content', e.target.value)}
                                    rows={12}
                                    className={`${inputBase} ${errors.content ? inputError : inputNormal} resize-y font-mono text-[13px] leading-relaxed`}
                                    placeholder={__('Write your document content here. You can use markdown for headings, bold text, and lists.', 'agentflow-ai')}
                                />
                                {errors.content && (
                                    <p className="flex items-center gap-1.5 mt-2 text-xs text-red-600 dark:text-red-400">
                                        <AlertCircle className="w-3.5 h-3.5" />
                                        {errors.content}
                                    </p>
                                )}
                            </div>
                        )}
                    </div>

                    {/* Short Description */}
                    <div>
                        <label className="block text-sm font-medium text-gray-700 dark:text-slate-300 mb-1.5">
                            {__('Short Description', 'agentflow-ai')}
                        </label>
                        <textarea
                            value={formData.description}
                            onChange={(e) => handleChange('description', e.target.value)}
                            rows={2}
                            maxLength={200}
                            className={`${inputBase} ${inputNormal} resize-none`}
                            placeholder={__('Briefly describe what this document contains (visible to AI to help it decide when to read it).', 'agentflow-ai')}
                        />
                        <div className="flex justify-between items-center mt-1">
                            <p className="text-[11px] text-gray-500 dark:text-slate-400">
                                {__('Keep it under 30 words for best AI performance.', 'agentflow-ai')}
                            </p>
                            <span className="text-[11px] text-gray-400">
                                {formData.description.length}/200
                            </span>
                        </div>
                    </div>

                    {/* Category & Status */}
                    <div className="grid grid-cols-1 md:grid-cols-2 gap-5 pt-2 border-t border-gray-100 dark:border-slate-700/50">
                        <div>
                            <label className="block text-sm font-medium text-gray-700 dark:text-slate-300 mb-1.5">
                                {__('Category', 'agentflow-ai')}
                            </label>
                            <div className="relative">
                                <select
                                    value={formData.category}
                                    onChange={(e) => handleChange('category', e.target.value)}
                                    className={`${inputBase} ${inputNormal} appearance-none pr-10 cursor-pointer`}
                                >
                                    {CATEGORIES.map((cat) => (
                                        <option key={cat.value} value={cat.value}>{cat.label}</option>
                                    ))}
                                </select>
                                <div className="absolute inset-y-0 right-0 flex items-center pr-3 pointer-events-none text-gray-400">
                                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2" strokeLinecap="round" strokeLinejoin="round">
                                        <path d="m6 9 6 6 6-6" />
                                    </svg>
                                </div>
                            </div>
                        </div>

                        <div className="flex items-center">
                            <label className="flex items-center gap-3 cursor-pointer mt-6">
                                <div className="relative">
                                    <input
                                        type="checkbox"
                                        checked={formData.is_active}
                                        onChange={(e) => handleChange('is_active', e.target.checked)}
                                        className="sr-only peer"
                                    />
                                    <div className="w-11 h-6 bg-gray-200 peer-focus:outline-none peer-focus:ring-4 peer-focus:ring-primary/20 dark:peer-focus:ring-primary/10 rounded-full peer dark:bg-slate-700 peer-checked:after:translate-x-full peer-checked:after:border-white after:content-[''] after:absolute after:top-[2px] after:left-[2px] after:bg-white after:border-gray-300 after:border after:rounded-full after:h-5 after:w-5 after:transition-all dark:border-gray-600 peer-checked:bg-primary"></div>
                                </div>
                                <div>
                                    <span className="text-sm font-medium text-gray-900 dark:text-white block">
                                        {__('Active Document', 'agentflow-ai')}
                                    </span>
                                    <span className="text-xs text-gray-500 dark:text-slate-400 hidden lg:block">
                                        {__('Available to AI agents', 'agentflow-ai')}
                                    </span>
                                </div>
                            </label>
                        </div>
                    </div>

                </div>
            </div>

            {/* Footer */}
            <div className="px-6 py-4 border-t border-gray-100 dark:border-slate-700 bg-gray-50/80 dark:bg-slate-800/80 flex items-center justify-end gap-3 shrink-0">
                <button
                    type="button"
                    onClick={onCancel}
                    disabled={isSaving}
                    className="px-4 py-2.5 text-sm font-medium rounded-xl text-gray-700 dark:text-slate-300 bg-white dark:bg-slate-800 border border-gray-200 dark:border-slate-600 hover:bg-gray-50 dark:hover:bg-slate-700 transition-colors disabled:opacity-50"
                >
                    {__('Cancel', 'agentflow-ai')}
                </button>
                <button
                    type="submit"
                    disabled={isSaving}
                    className="inline-flex items-center justify-center gap-2 px-6 py-2.5 text-sm font-medium rounded-xl bg-primary text-white hover:bg-primary/90 shadow-sm transition-all duration-200 active:scale-95 disabled:opacity-70 disabled:active:scale-100 min-w-[140px]"
                >
                    {isSaving ? (
                        <>
                            <svg className="animate-spin -ml-1 mr-2 h-4 w-4 text-white" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                                <circle className="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" strokeWidth="4"></circle>
                                <path className="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                            </svg>
                            {__('Saving...', 'agentflow-ai')}
                        </>
                    ) : (
                        item ? __('Update Document', 'agentflow-ai') : __('Save Document', 'agentflow-ai')
                    )}
                </button>
            </div>
        </form>
    );
}

KnowledgeEditor.propTypes = {
    item: PropTypes.object,
    onSave: PropTypes.func.isRequired,
    onCancel: PropTypes.func.isRequired,
};
