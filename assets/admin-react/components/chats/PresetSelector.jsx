/**
 * Preset Selector Component
 *
 * Displays available widget presets for quick setup.
 * Shows when creating a new widget.
 */
import { useState } from '@wordpress/element';
import { __ } from '@wordpress/i18n';
import PropTypes from 'prop-types';
import { Button, cn } from '../ui';
import { WIDGET_PRESETS } from './widget-presets';

const ICONS = {
    rocket: <svg className="w-8 h-8" fill="none" viewBox="0 0 24 24" stroke="currentColor" strokeWidth={1.5}><path strokeLinecap="round" strokeLinejoin="round" d="M15.59 14.37a6 6 0 01-5.84 7.38v-4.8m5.84-2.58a14.98 14.98 0 006.16-12.12A14.98 14.98 0 009.631 8.41m5.96 5.96a14.926 14.926 0 01-5.841 2.58m-.119-8.54a6 6 0 00-7.381 5.84h4.8m2.581-5.84a14.927 14.927 0 00-2.58 5.84m2.699 2.7c-.103.021-.207.041-.311.06a15.09 15.09 0 01-2.448-2.448 14.9 14.9 0 01.06-.312m-2.24 2.39a4.493 4.493 0 00-1.757 4.306 4.493 4.493 0 004.306-1.758M16.5 9a1.5 1.5 0 11-3 0 1.5 1.5 0 013 0z" /></svg>,
    cart: <svg className="w-6 h-6" fill="none" viewBox="0 0 24 24" stroke="currentColor" strokeWidth={1.5}><path strokeLinecap="round" strokeLinejoin="round" d="M2.25 3h1.386c.51 0 .955.343 1.087.835l.383 1.437M7.5 14.25a3 3 0 00-3 3h15.75m-12.75-3h11.218c1.121-2.3 2.1-4.684 2.924-7.138a60.114 60.114 0 00-16.536-1.84M7.5 14.25L5.106 5.272M6 20.25a.75.75 0 11-1.5 0 .75.75 0 011.5 0zm12.75 0a.75.75 0 11-1.5 0 .75.75 0 011.5 0z" /></svg>,
    target: <svg className="w-6 h-6" fill="none" viewBox="0 0 24 24" stroke="currentColor" strokeWidth={1.5}><path strokeLinecap="round" strokeLinejoin="round" d="M3.75 13.5l10.5-11.25L12 10.5h8.25L9.75 21.75 12 13.5H3.75z" /></svg>,
    headset: <svg className="w-6 h-6" fill="none" viewBox="0 0 24 24" stroke="currentColor" strokeWidth={1.5}><path strokeLinecap="round" strokeLinejoin="round" d="M12 18.75a6 6 0 006-6v-1.5m-6 7.5a6 6 0 01-6-6v-1.5m6 7.5v3.75m-3.75 0h7.5M12 15.75a3 3 0 01-3-3V4.5a3 3 0 116 0v8.25a3 3 0 01-3 3z" /></svg>,
    briefcase: <svg className="w-6 h-6" fill="none" viewBox="0 0 24 24" stroke="currentColor" strokeWidth={1.5}><path strokeLinecap="round" strokeLinejoin="round" d="M20.25 14.15v4.25c0 1.094-.787 2.036-1.872 2.18-2.087.277-4.216.42-6.378.42s-4.291-.143-6.378-.42c-1.085-.144-1.872-1.086-1.872-2.18v-4.25m16.5 0a2.18 2.18 0 00.75-1.661V8.706c0-1.081-.768-2.015-1.837-2.175a48.114 48.114 0 00-3.413-.387m4.5 8.006c-.194.165-.42.295-.673.38A23.978 23.978 0 0112 15.75c-2.648 0-5.195-.429-7.577-1.22a2.016 2.016 0 01-.673-.38m0 0A2.18 2.18 0 013 12.489V8.706c0-1.081.768-2.015 1.837-2.175a48.111 48.111 0 013.413-.387m7.5 0V5.25A2.25 2.25 0 0013.5 3h-3a2.25 2.25 0 00-2.25 2.25v.894m7.5 0a48.667 48.667 0 00-7.5 0M12 12.75h.008v.008H12v-.008z" /></svg>,
    wave: <svg className="w-6 h-6" fill="none" viewBox="0 0 24 24" stroke="currentColor" strokeWidth={1.5}><path strokeLinecap="round" strokeLinejoin="round" d="M10.05 4.575a1.575 1.575 0 10-3.15 0v3m3.15-3v-1.5a1.575 1.575 0 013.15 0v1.5m-3.15 0l.075 5.925m3.075-5.925a1.575 1.575 0 20-3.15 0v3m3.15-3v1.5m0 6v-6a1.575 1.575 0 113.15 0v5.85l-2.925 8.925h-9.9l-1-7.2-2.1-.9a1.575 1.575 0 01.9-3l2.85 1.2 1.35 6" /></svg>,
    diamond: <svg className="w-6 h-6" fill="none" viewBox="0 0 24 24" stroke="currentColor" strokeWidth={1.5}><path strokeLinecap="round" strokeLinejoin="round" d="M12 2L2 9.5 12 22 22 9.5 12 2z" /><path strokeLinecap="round" strokeLinejoin="round" d="M2 9.5h20" /></svg>,
    cpu: <svg className="w-6 h-6" fill="none" viewBox="0 0 24 24" stroke="currentColor" strokeWidth={1.5}><path strokeLinecap="round" strokeLinejoin="round" d="M8.25 3v1.5M4.5 8.25H3m18 0h-1.5M4.5 12H3m18 0h-1.5m-15 3.75H3m18 0h-1.5M8.25 19.5V21M12 3v1.5m0 15V21m3.75-18v1.5m0 15V21m-9-1.5h10.5a2.25 2.25 0 002.25-2.25V6.75a2.25 2.25 0 00-2.25-2.25H6.75A2.25 2.25 0 004.5 6.75v10.5a2.25 2.25 0 002.25 2.25z" /><path strokeLinecap="round" strokeLinejoin="round" d="M9 9h6v6H9V9z" /></svg>,
    maximize: <svg className="w-6 h-6" fill="none" viewBox="0 0 24 24" stroke="currentColor" strokeWidth={1.5}><path strokeLinecap="round" strokeLinejoin="round" d="M3.75 3.75v4.5m0-4.5h4.5m-4.5 0L9 9m11.25-5.25v4.5m0-4.5h-4.5m4.5 0L15 9m-11.25 11.25v-4.5m0 4.5h4.5m-4.5 0L9 15m11.25 5.25v-4.5m0 4.5h-4.5m4.5 0L15 15" /></svg>,
    camera: <svg className="w-6 h-6" fill="none" viewBox="0 0 24 24" stroke="currentColor" strokeWidth={1.5}><path strokeLinecap="round" strokeLinejoin="round" d="M6.827 6.175A2.31 2.31 0 015.186 7.23c-.38.054-.757.112-1.134.175C2.999 7.58 2.25 8.507 2.25 9.574V18a2.25 2.25 0 002.25 2.25h15A2.25 2.25 0 0021.75 18V9.574c0-1.067-.75-1.994-1.802-2.169a47.865 47.865 0 00-1.134-.175 2.31 2.31 0 01-1.64-1.055l-.822-1.316a2.192 2.192 0 00-1.736-1.039 48.774 48.774 0 00-5.232 0 2.192 2.192 0 00-1.736 1.039l-.821 1.316z" /><path strokeLinecap="round" strokeLinejoin="round" d="M16.5 12.75a4.5 4.5 0 11-9 0 4.5 4.5 0 019 0z" /></svg>,
};

export default function PresetSelector({ onSelect, onSkip }) {
    const [selectedPreset, setSelectedPreset] = useState(null);

    const handleApply = () => {
        if (selectedPreset) {
            onSelect(selectedPreset);
        }
    };

    return (
        <div className="space-y-6">
            {/* Header */}
            <div className="text-center">
                <div className="inline-flex items-center justify-center w-16 h-16 rounded-2xl bg-gradient-to-br from-primary/20 to-primary/5 mb-4 text-primary">
                    {ICONS.rocket}
                </div>
                <h2 className="text-2xl font-bold text-slate-900">
                    {__('Quick Start Templates', 'agentflow-ai')}
                </h2>
                <p className="mt-2 text-slate-600 max-w-lg mx-auto">
                    {__('Choose a pre-configured template to get started quickly, or start from scratch.', 'agentflow-ai')}
                </p>
            </div>

            {/* Preset Grid */}
            <div className="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
                {WIDGET_PRESETS.map((preset) => {
                    const isSelected = selectedPreset?.id === preset.id;
                    return (
                        <button
                            key={preset.id}
                            type="button"
                            onClick={() => setSelectedPreset(preset)}
                            className={cn(
                                'relative text-left p-5 rounded-xl border-2 transition-all hover:shadow-md',
                                isSelected
                                    ? 'border-primary bg-primary/5 shadow-md ring-2 ring-primary/20'
                                    : 'border-slate-200 bg-white hover:border-slate-300'
                            )}
                        >
                            {/* Selected Check */}
                            {isSelected && (
                                <div className="absolute top-3 right-3 w-6 h-6 rounded-full bg-primary flex items-center justify-center">
                                    <svg className="w-4 h-4 text-white" fill="currentColor" viewBox="0 0 20 20">
                                        <path fillRule="evenodd" d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z" clipRule="evenodd" />
                                    </svg>
                                </div>
                            )}

                            {/* Icon & Title */}
                            <div className="flex items-center gap-3 mb-3">
                                <span className="text-primary">{ICONS[preset.icon] || preset.icon}</span>
                                <h3 className="font-semibold text-slate-900">{preset.name}</h3>
                            </div>

                            {/* Description */}
                            <p className="text-sm text-slate-600 mb-4">
                                {preset.description}
                            </p>

                            {/* Features Tags */}
                            <div className="flex flex-wrap gap-1.5">
                                {preset.config.engagement?.quick_replies?.length > 0 && (
                                    <span className="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium bg-emerald-100 text-emerald-700">
                                        {preset.config.engagement.quick_replies.length} Quick Questions
                                    </span>
                                )}
                                {preset.config.behavior?.exit_intent_enabled && (
                                    <span className="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium bg-amber-100 text-amber-700">
                                        Exit Intent
                                    </span>
                                )}
                                {preset.config.behavior?.auto_open_enabled && (
                                    <span className="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium bg-blue-100 text-blue-700">
                                        Auto-Open
                                    </span>
                                )}
                                {preset.config.display?.schedule_enabled && (
                                    <span className="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium bg-purple-100 text-purple-700">
                                        Scheduled
                                    </span>
                                )}
                                {preset.config.behavior?.cart_abandonment_enabled && (
                                    <span className="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium bg-rose-100 text-rose-700">
                                        Cart Recovery
                                    </span>
                                )}
                            </div>
                        </button>
                    );
                })}
            </div>

            {/* Actions */}
            <div className="flex items-center justify-center gap-4 pt-4 border-t border-slate-100">
                <Button variant="ghost" onClick={onSkip}>
                    {__('Start from Scratch', 'agentflow-ai')}
                </Button>
                <Button
                    variant="primary"
                    onClick={handleApply}
                    disabled={!selectedPreset}
                >
                    {selectedPreset
                        ? __('Use This Template', 'agentflow-ai')
                        : __('Select a Template', 'agentflow-ai')
                    }
                </Button>
            </div>
        </div>
    );
}

PresetSelector.propTypes = {
    onSelect: PropTypes.func.isRequired,
    onSkip: PropTypes.func.isRequired,
};
