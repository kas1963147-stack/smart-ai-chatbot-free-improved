const fs = require('fs');
const path = require('path');

const targetFile = 'd:\\Quarksol-AI-Chatbot-php-without-ai-site-builder\\assets\\admin-react\\components\\providers\\ProviderHubPage.jsx';
let content = fs.readFileSync(targetFile, 'utf8');

// 1. Split ProviderHubPage.jsx into true dedicated page by extracting form UI upward into if(showForm)
// But to make it easier to replace, I will inject the return early.
const formStartRegex = /\{\/\* ── Instance Form ── \*\/\}\s*\{showForm && \(\s*<div className="rounded-2xl bg-white dark:bg-gray-800 border border-gray-200 dark:border-gray-700 shadow-xl overflow-hidden">/g;

// First let's clean up Metronic classes in the form
content = content.replace(
    /className="block text-sm font-semibold text-gray-700 dark:text-gray-300 mb-1\.5"/g,
    'className="swc-label block"'
);

// Generic inputs
const inputClassPattern = /className="w-full px-4 py-2\.5[^"]*"/g;
content = content.replace(inputClassPattern, (match) => {
    let newClass = 'swc-input w-full ';
    if (match.includes('pr-10')) newClass += 'pr-10 ';
    if (match.includes('font-mono')) newClass += 'font-mono text-sm ';
    if (match.includes('transition cursor-pointer')) newClass += 'cursor-pointer ';
    if (match.includes('disabled:opacity-60')) newClass += 'disabled:opacity-60 ';
    if (match.includes('opacity-60')) newClass += 'opacity-60 ';
    // if it's a select
    if (match.includes('swc-select') === false && match.includes('bg-white')) {
        return 'className="' + newClass.trim() + '"';
    }
    return match;
});

// Select specific
content = content.replace(/className="swc-input w-full disabled:opacity-60"/g, 'className="swc-select w-full px-4 py-2.5 disabled:opacity-60"');

// Prices inputs
content = content.replace(/className="w-full px-4 py-2 rounded-lg border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-700 text-gray-900 dark:text-white focus:ring-2 focus:ring-primary transition font-mono text-sm"/g, 'className="swc-input w-full font-mono"');

// Buttons inside the form Actions area
content = content.replace(/className="px-6 py-2\.5 bg-primary hover:bg-primary\/90 text-white rounded-xl font-semibold text-sm shadow-lg shadow-primary\/20 dark:shadow-primary\/40 transition-all disabled:opacity-50 disabled:cursor-not-allowed"/g, 'className="swc-btn swc-btn--primary px-6 py-2.5 disabled:opacity-50 disabled:cursor-not-allowed"');
content = content.replace(/className="px-5 py-2\.5 bg-gray-100 dark:bg-gray-700 hover:bg-gray-200 dark:hover:bg-gray-600 text-gray-700 dark:text-gray-300 rounded-xl font-medium text-sm transition"/g, 'className="swc-btn swc-btn--secondary px-5 py-2.5"');
content = content.replace(/className="px-5 py-2\.5 text-gray-500 hover:text-gray-700 dark:text-gray-400 dark:hover:text-gray-200 text-sm font-medium transition"/g, 'className="swc-btn swc-btn--ghost px-5 py-2.5"');

// Header for the form block
content = content.replace(/className="bg-gradient-to-r from-primary\/\[0\.05\] to-primary\/\[0\.02\] dark:from-primary\/20 dark:to-primary\/10 px-6 py-4 border-b border-gray-200 dark:border-gray-700"/g, 'className="px-6 py-5 border-b border-gray-200 dark:border-gray-700"');
content = content.replace(/className="text-lg font-bold text-gray-900 dark:text-white"/g, 'className="text-xl font-bold text-gray-900 dark:text-white"');

// Remove instance form from the main return and create a separate standalone return
const mainReturnIndex = content.indexOf('return (\n        <div className="space-y-6">');
if (mainReturnIndex > -1) {
    const beforeReturn = content.substring(0, mainReturnIndex);
    const afterReturn = content.substring(mainReturnIndex);
    
    // We will extract the form block using its wrapper
    const formRegex = /\{\/\* ── Instance Form ── \*\/\}\s*\{showForm && \(\s*<div className="rounded-2xl bg-white dark:bg-gray-800 border border-gray-200 dark:border-gray-700 shadow-xl overflow-hidden">([\s\S]*?)<\/div>\s*\)\}/;
    const formMatch = afterReturn.match(formRegex);
    if (formMatch) {
         let formBlock = formMatch[1]; // The inside of the div
         
         // Fix the wrapping div with swc-card
         formBlock = `
    // ─── Render Dedicated Form Page ───
    if (showForm) {
        return (
            <div className="swc-admin w-full space-y-6 animate-in fade-in slide-in-from-bottom-2 duration-300">
                {/* Back Button */}
                <div className="flex items-center justify-between">
                    <button
                        onClick={() => { setShowForm(false); setEditing(false); setForm({ ...EMPTY_FORM }); setTestResult(null); }}
                        className="swc-btn swc-btn--ghost inline-flex items-center gap-2 px-1 py-2 text-sm"
                    >
                        <svg className="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M10 19l-7-7m0 0l7-7m-7 7h18" /></svg>
                        <span className="font-semibold">{__('Back to Providers', 'smartwoo-chatbot')}</span>
                    </button>
                    {notice && (
                        <div className={\`px-4 py-2 text-sm font-medium rounded-lg shadow-sm animate-in fade-in \${notice.type === 'success' ? 'bg-emerald-50 text-emerald-800' : 'bg-red-50 text-red-800'}\`}>
                            {notice.message}
                        </div>
                    )}
                </div>
                {/* Main Form Card */}
                <div className="swc-card overflow-hidden">
                    ${formBlock}
                </div>
            </div>
        );
    }
    
    // ─── Render Setup & List Page ───
`;
         // Replace the main return block
         const patchedAfterReturn = afterReturn.replace(formRegex, '').replace('{/* ── Add Button ── */}', '');
         const finalAfterReturn = patchedAfterReturn.replace('{!showForm && (', '{true && (');
         
         content = beforeReturn + formBlock + finalAfterReturn;
    }
}

// Ensure the button inside list isn't using tailwind bg
content = content.replace(/className="inline-flex items-center gap-2 px-5 py-3 bg-primary hover:bg-primary\/90 text-white rounded-xl font-semibold text-sm shadow-lg shadow-primary\/20 dark:shadow-primary\/50 transition-all hover:scale-\[1.02\]"/g, 'className="swc-btn swc-btn--primary inline-flex items-center gap-2 px-5 py-3 shadow-lg hover:scale-[1.02] active:scale-95"');
content = content.replace(/className="inline-flex items-center gap-1\.5 px-4 py-2 bg-primary\/10 hover:bg-primary\/20 text-primary dark:text-primary-foreground rounded-lg font-semibold text-xs transition border border-primary\/20 dark:border-primary\/30"/g, 'className="swc-btn swc-btn--secondary inline-flex items-center gap-1.5 px-4 py-2 text-xs font-semibold"');

// Fix the empty state list
content = content.replace(/instances\.length === 0 && !showForm/g, 'instances.length === 0');
content = content.replace(/!showForm && \(/g, 'true && (');

fs.writeFileSync(targetFile, content);

console.log("Updated ProviderHubPage.jsx");
