/**
 * Display Rules Configuration
 *
 * Control where and when the widget appears.
 * Includes: URL targeting, visitor targeting, device targeting, schedule.
 */
import { useState, useEffect } from '@wordpress/element';
import { __ } from '@wordpress/i18n';
import PropTypes from 'prop-types';
import { Panel, PanelBody, Toggle, cn, MultiSelect } from '../ui';

const API_ROOT = window.swcChatbot?.siteUrl || window.location.origin;

const DAYS_OF_WEEK = [
    { id: 0, label: 'Sun' },
    { id: 1, label: 'Mon' },
    { id: 2, label: 'Tue' },
    { id: 3, label: 'Wed' },
    { id: 4, label: 'Thu' },
    { id: 5, label: 'Fri' },
    { id: 6, label: 'Sat' },
];

const DEVICES = [
    { id: 'desktop', label: 'Desktop', icon: <svg className="w-6 h-6" fill="none" viewBox="0 0 24 24" stroke="currentColor" strokeWidth={1.5}><path strokeLinecap="round" strokeLinejoin="round" d="M9.75 17L9 20l-1 1h8l-1-1-.75-3M3 13h18M5 17h14a2 2 0 002-2V5a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z" /></svg> },
    { id: 'tablet', label: 'Tablet', icon: <svg className="w-6 h-6" fill="none" viewBox="0 0 24 24" stroke="currentColor" strokeWidth={1.5}><path strokeLinecap="round" strokeLinejoin="round" d="M12 18h.01M7 21h10a2 2 0 002-2V5a2 2 0 00-2-2H7a2 2 0 00-2 2v14a2 2 0 002 2z" /></svg> },
    { id: 'mobile', label: 'Mobile', icon: <svg className="w-6 h-6" fill="none" viewBox="0 0 24 24" stroke="currentColor" strokeWidth={1.5}><path strokeLinecap="round" strokeLinejoin="round" d="M12 18h.01M8 21h8a2 2 0 002-2V5a2 2 0 00-2-2H8a2 2 0 00-2 2v14a2 2 0 002 2z" /></svg> },
];

export default function DisplayRules({ display, onChange }) {
    const [pages, setPages] = useState([]);
    const [loadingPages, setLoadingPages] = useState(true);

    useEffect(() => {
        const loadContent = async () => {
            try {
                setLoadingPages(true);
                const [pagesResp, postsResp] = await Promise.all([
                    fetch(`${API_ROOT}/wp-json/wp/v2/pages?per_page=100&status=publish`, {
                        headers: { 'X-WP-Nonce': window.swcChatbot?.nonce },
                    }),
                    fetch(`${API_ROOT}/wp-json/wp/v2/posts?per_page=100&status=publish`, {
                        headers: { 'X-WP-Nonce': window.swcChatbot?.nonce },
                    }),
                ]);

                const pagesData = await pagesResp.json();
                const postsData = await postsResp.json();

                const combineOptions = (items) => {
                    if (!Array.isArray(items)) return [];
                    return items.map((item) => {
                        let path = item.link;
                        try {
                            path = new URL(item.link).pathname;
                        } catch (e) { }
                        return {
                            id: path,
                            value: path,
                            label: item.title?.rendered || item.title || `#${item.id}`
                        };
                    });
                };

                const pageOptions = combineOptions(pagesData);
                const postOptions = combineOptions(postsData);

                // Include any existing URLs in the options list so they aren't lost
                const existingUrls = [
                    ...(display.include_urls || []),
                    ...(display.exclude_urls || [])
                ].filter((v, i, a) => a.indexOf(v) === i); // distinct

                const currentOptionsPaths = new Set([
                    ...pageOptions.map(o => o.value),
                    ...postOptions.map(o => o.value)
                ]);

                const extraOptions = existingUrls
                    .filter(url => !currentOptionsPaths.has(url))
                    .map(url => ({ value: url, label: `Custom: ${url}` }));

                setPages([
                    { value: '', label: __('Leave empty to target all URLs', 'smart-woo-chatbot'), disabled: true },
                    ...extraOptions,
                    ...pageOptions.map(o => ({ ...o, label: `Page: ${o.label}` })),
                    ...postOptions.map(o => ({ ...o, label: `Post: ${o.label}` }))
                ]);
            } catch (err) {
                console.error('Failed to load pages/posts', err);
            } finally {
                setLoadingPages(false);
            }
        };
        loadContent();
        // eslint-disable-next-line react-hooks/exhaustive-deps
    }, []);

    const handleChange = (field, value) => {
        onChange({ ...display, [field]: value });
    };

    const handleUrlChangeSelect = (type, value) => {
        const urls = (Array.isArray(value) ? value : [value])
            .filter((v) => v !== '')
            .map((u) => u.trim())
            .filter(Boolean);
        handleChange(type, urls);
    };

    const toggleDay = (dayId) => {
        const days = display.schedule_days || [1, 2, 3, 4, 5];
        const newDays = days.includes(dayId)
            ? days.filter((d) => d !== dayId)
            : [...days, dayId].sort();
        handleChange('schedule_days', newDays);
    };

    const toggleDevice = (deviceId) => {
        const devices = display.devices || ['desktop', 'tablet', 'mobile'];
        const newDevices = devices.includes(deviceId)
            ? devices.filter((d) => d !== deviceId)
            : [...devices, deviceId];
        handleChange('devices', newDevices.length > 0 ? newDevices : ['desktop']);
    };

    return (
        <div className="space-y-6">
            <div>
                <h3 className="text-lg font-semibold text-slate-900">
                    {__('Display Rules', 'smart-woo-chatbot')}
                </h3>
                <p className="mt-1 text-sm text-slate-500">
                    {__('Control where and when this widget appears on your site.', 'smart-woo-chatbot')}
                </p>
            </div>

            <Panel>
                {/* URL Targeting */}
                <PanelBody title={__('URL Targeting', 'smart-woo-chatbot')} initialOpen>
                    <div className="space-y-4">
                        {loadingPages ? (
                            <div className="text-sm text-gray-500">{__('Loading pages...', 'smart-woo-chatbot')}</div>
                        ) : (
                            <>
                                <MultiSelect
                                    label={__('Show Only On (Include Pages)', 'smart-woo-chatbot')}
                                    options={pages}
                                    value={display.include_urls || []}
                                    onChange={(val) => handleUrlChangeSelect('include_urls', val)}
                                    placeholder={__('Select pages to show widget...', 'smart-woo-chatbot')}
                                    help={__('Leave empty to show everywhere.', 'smart-woo-chatbot')}
                                />
                                <MultiSelect
                                    label={__('Hide On (Exclude Pages)', 'smart-woo-chatbot')}
                                    options={pages}
                                    value={display.exclude_urls || []}
                                    onChange={(val) => handleUrlChangeSelect('exclude_urls', val)}
                                    placeholder={__('Select pages to hide widget...', 'smart-woo-chatbot')}
                                />
                            </>
                        )}
                    </div>
                </PanelBody>

                {/* Visitor Targeting */}
                <PanelBody title={__('Visitor Targeting', 'smart-woo-chatbot')}>
                    <div className="space-y-4">
                        <Toggle
                            label={__('Logged-In Users Only', 'smart-woo-chatbot')}
                            help={__('Only show to authenticated WordPress users.', 'smart-woo-chatbot')}
                            checked={display.logged_in_only || false}
                            onChange={(val) => handleChange('logged_in_only', val)}
                        />
                        <Toggle
                            label={__('Guests Only', 'smart-woo-chatbot')}
                            help={__('Only show to visitors who are not logged in.', 'smart-woo-chatbot')}
                            checked={display.guest_only || false}
                            onChange={(val) => handleChange('guest_only', val)}
                            disabled={display.logged_in_only}
                        />
                    </div>
                </PanelBody>

                {/* Device Targeting */}
                <PanelBody title={__('Device Targeting', 'smart-woo-chatbot')}>
                    <div className="space-y-3">
                        <p className="text-sm text-gray-600">
                            {__('Show widget on these devices:', 'smart-woo-chatbot')}
                        </p>
                        <div className="flex gap-3">
                            {DEVICES.map((device) => {
                                const isActive = (display.devices || ['desktop', 'tablet', 'mobile']).includes(device.id);
                                return (
                                    <button
                                        key={device.id}
                                        type="button"
                                        onClick={() => toggleDevice(device.id)}
                                        className={cn(
                                            'flex-1 flex flex-col items-center gap-1 p-3 rounded-lg border-2 transition-all',
                                            isActive
                                                ? 'border-primary bg-primary/5 text-primary'
                                                : 'border-gray-200 text-gray-500 hover:border-gray-300'
                                        )}
                                    >
                                        <span>{device.icon}</span>
                                        <span className="text-xs font-medium">{device.label}</span>
                                    </button>
                                );
                            })}
                        </div>
                    </div>
                </PanelBody>

                {/* Schedule */}
                <PanelBody title={__('Display Schedule', 'smart-woo-chatbot')}>
                    <div className="space-y-4">
                        <Toggle
                            label={__('Enable Schedule', 'smart-woo-chatbot')}
                            help={__('Only show widget during specific hours.', 'smart-woo-chatbot')}
                            checked={display.schedule_enabled || false}
                            onChange={(val) => handleChange('schedule_enabled', val)}
                        />

                        {display.schedule_enabled && (
                            <>
                                <div className="grid grid-cols-2 gap-4">
                                    <div>
                                        <label className="block text-sm font-medium text-gray-700 mb-1.5">
                                            {__('Start Time', 'smart-woo-chatbot')}
                                        </label>
                                        <input
                                            type="time"
                                            className="w-full h-10 px-3 text-sm rounded-lg border border-gray-300 bg-white text-gray-900 focus:outline-none focus:ring-2 focus:ring-primary/30 focus:border-primary"
                                            value={display.schedule_start || '09:00'}
                                            onChange={(e) => handleChange('schedule_start', e.target.value)}
                                        />
                                    </div>
                                    <div>
                                        <label className="block text-sm font-medium text-gray-700 mb-1.5">
                                            {__('End Time', 'smart-woo-chatbot')}
                                        </label>
                                        <input
                                            type="time"
                                            className="w-full h-10 px-3 text-sm rounded-lg border border-gray-300 bg-white text-gray-900 focus:outline-none focus:ring-2 focus:ring-primary/30 focus:border-primary"
                                            value={display.schedule_end || '17:00'}
                                            onChange={(e) => handleChange('schedule_end', e.target.value)}
                                        />
                                    </div>
                                </div>

                                <div>
                                    <label className="block text-sm font-medium text-gray-700 mb-2">
                                        {__('Days of Week', 'smart-woo-chatbot')}
                                    </label>
                                    <div className="flex gap-1">
                                        {DAYS_OF_WEEK.map((day) => {
                                            const isActive = (display.schedule_days || [1, 2, 3, 4, 5]).includes(day.id);
                                            return (
                                                <button
                                                    key={day.id}
                                                    type="button"
                                                    onClick={() => toggleDay(day.id)}
                                                    className={cn(
                                                        'flex-1 py-2 text-xs font-medium rounded-lg border transition-all',
                                                        isActive
                                                            ? 'border-primary bg-primary text-white'
                                                            : 'border-gray-200 text-gray-500 hover:border-gray-300'
                                                    )}
                                                >
                                                    {day.label}
                                                </button>
                                            );
                                        })}
                                    </div>
                                </div>
                            </>
                        )}
                    </div>
                </PanelBody>
            </Panel>
        </div>
    );
}

DisplayRules.propTypes = {
    display: PropTypes.object.isRequired,
    onChange: PropTypes.func.isRequired,
};
