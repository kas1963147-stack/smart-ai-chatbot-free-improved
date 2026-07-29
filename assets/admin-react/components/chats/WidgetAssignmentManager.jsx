/**
 * WidgetAssignmentManager
 *
 * UI for assigning widgets to pages/posts.
 */
import { useEffect, useMemo, useState } from '@wordpress/element';
import { __ } from '@wordpress/i18n';
import PropTypes from 'prop-types';
import useChatsApi from '../../hooks/useChatsApi';

const ASSIGNMENT_TYPES = [
	{ value: 'page', label: __('Specific Page', 'smart-woo-chatbot') },
	{ value: 'post', label: __('Specific Post', 'smart-woo-chatbot') },
	{ value: 'all_posts', label: __('All Posts', 'smart-woo-chatbot') },
	{ value: 'homepage', label: __('Homepage', 'smart-woo-chatbot') },
	{ value: 'woocommerce_shop', label: __('WooCommerce Shop', 'smart-woo-chatbot') },
	{ value: 'woocommerce_product', label: __('WooCommerce Product Pages', 'smart-woo-chatbot') },
	{ value: 'woocommerce_cart', label: __('WooCommerce Cart', 'smart-woo-chatbot') },
	{ value: 'woocommerce_checkout', label: __('WooCommerce Checkout', 'smart-woo-chatbot') },
];

const API_ROOT = window.swcChatbot?.siteUrl || window.location.origin;

export default function WidgetAssignmentManager({ widgetId }) {
	const {
		fetchAssignments,
		createAssignment,
		deleteAssignment,
	} = useChatsApi();

	const [assignments, setAssignments] = useState([]);
	const [loading, setLoading] = useState(true);
	const [error, setError] = useState(null);
	const [assignmentType, setAssignmentType] = useState('page');
	const [pages, setPages] = useState([]);
	const [posts, setPosts] = useState([]);
	const [selectedPostId, setSelectedPostId] = useState('');

	useEffect(() => {
		if (!widgetId) return;
		loadAssignments();
		loadContent();
		// eslint-disable-next-line react-hooks/exhaustive-deps
	}, [widgetId]);

	const loadAssignments = async () => {
		try {
			setLoading(true);
			const data = await fetchAssignments(widgetId);
			setAssignments(Array.isArray(data) ? data : []);
		} catch (err) {
			setError(err.message || __('Failed to load assignments', 'smart-woo-chatbot'));
		} finally {
			setLoading(false);
		}
	};

	const loadContent = async () => {
		try {
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
			setPages(Array.isArray(pagesData) ? pagesData : []);
			setPosts(Array.isArray(postsData) ? postsData : []);
		} catch (err) {
			// Non-critical
		}
	};

	const availablePosts = assignmentType === 'page' ? pages : posts;

	const canSelectPost = assignmentType === 'page' || assignmentType === 'post';

	const addAssignment = async () => {
		setError(null);

		let payload = {
			widget_id: widgetId,
			assignment_type: assignmentType,
			post_id: 0,
			post_type: null,
		};

		if (assignmentType === 'page' || assignmentType === 'post') {
			if (!selectedPostId) {
				setError(__('Please select a page or post first.', 'smart-woo-chatbot'));
				return;
			}
			payload.post_id = parseInt(selectedPostId, 10);
			payload.post_type = assignmentType === 'page' ? 'page' : 'post';
		}

		if (assignmentType === 'all_posts') {
			payload.post_type = 'post';
		}

		if (assignmentType === 'woocommerce_product') {
			payload.post_type = 'product';
		}

		try {
			const resp = await createAssignment(payload);
			if (resp?.success === false) {
				throw new Error(resp?.message || __('Failed to create assignment', 'smart-woo-chatbot'));
			}
			setSelectedPostId('');
			await loadAssignments();
		} catch (err) {
			setError(err.message || __('Failed to create assignment', 'smart-woo-chatbot'));
		}
	};

	const handleDelete = async (assignmentId) => {
		if (!confirm(__('Remove this assignment?', 'smart-woo-chatbot'))) return;
		try {
			await deleteAssignment(assignmentId, widgetId);
			await loadAssignments();
		} catch (err) {
			setError(err.message || __('Failed to delete assignment', 'smart-woo-chatbot'));
		}
	};

	const formattedAssignments = useMemo(() => {
		return assignments.map((assignment) => {
			let label = assignment.assignment_type || assignment.type || 'assignment';
			if (assignment.post_title) {
				label = assignment.post_title;
			}
			return {
				...assignment,
				label,
			};
		});
	}, [assignments]);

	return (
		<div className="space-y-4">
			{error && (
				<div className="px-4 py-3 rounded-lg bg-red-50 text-red-700 border border-red-200 text-sm">
					{error}
				</div>
			)}

			<div className="bg-gray-50/60 border border-gray-200 rounded-xl p-4">
				<div className="grid grid-cols-1 md:grid-cols-3 gap-4">
					<div>
						<label className="block text-sm font-medium text-gray-700 mb-1.5">
							{__('Assignment Type', 'smart-woo-chatbot')}
						</label>
						<select
							value={assignmentType}
							onChange={(e) => setAssignmentType(e.target.value)}
							className="w-full h-10 px-4 text-sm rounded-lg border border-gray-300 bg-white text-gray-900"
						>
							{ASSIGNMENT_TYPES.map((option) => (
								<option key={option.value} value={option.value}>{option.label}</option>
							))}
						</select>
					</div>

					{canSelectPost && (
						<div>
							<label className="block text-sm font-medium text-gray-700 mb-1.5">
								{assignmentType === 'page' ? __('Page', 'smart-woo-chatbot') : __('Post', 'smart-woo-chatbot')}
							</label>
							<select
								value={selectedPostId}
								onChange={(e) => setSelectedPostId(e.target.value)}
								className="w-full h-10 px-4 text-sm rounded-lg border border-gray-300 bg-white text-gray-900"
							>
								<option value="">{__('Select…', 'smart-woo-chatbot')}</option>
								{availablePosts.map((post) => (
									<option key={post.id} value={post.id}>
										{post.title?.rendered || post.title || `#${post.id}`}
									</option>
								))}
							</select>
						</div>
					)}

					<div className="flex items-end">
						<button
							type="button"
							onClick={addAssignment}
							className="inline-flex items-center px-4 py-2 text-sm font-medium rounded-lg bg-primary text-white hover:bg-primary/90 transition-colors"
						>
							{__('Add Assignment', 'smart-woo-chatbot')}
						</button>
					</div>
				</div>
			</div>

			<div className="bg-white border border-gray-200 rounded-xl">
				<div className="px-4 py-3 border-b border-gray-100">
					<h4 className="text-sm font-semibold text-gray-900">
						{__('Current Assignments', 'smart-woo-chatbot')}
					</h4>
				</div>
				<div className="p-4">
					{loading ? (
						<div className="text-sm text-gray-500">{__('Loading assignments…', 'smart-woo-chatbot')}</div>
					) : formattedAssignments.length === 0 ? (
						<div className="text-sm text-gray-500">{__('No assignments yet.', 'smart-woo-chatbot')}</div>
					) : (
						<div className="space-y-2">
							{formattedAssignments.map((assignment) => (
								<div
									key={assignment.id}
									className="flex items-center justify-between px-3 py-2 rounded-lg border border-gray-200"
								>
									<div>
										<p className="text-sm font-medium text-gray-900">
											{assignment.label}
										</p>
										<p className="text-xs text-gray-500 capitalize">
											{assignment.assignment_type || assignment.type || ''}
										</p>
									</div>
									<button
										onClick={() => handleDelete(assignment.id)}
										className="text-xs text-red-600 hover:text-red-700 hover:bg-red-50 px-2 py-1 rounded"
									>
										{__('Remove', 'smart-woo-chatbot')}
									</button>
								</div>
							))}
						</div>
					)}
				</div>
			</div>
		</div>
	);
}

WidgetAssignmentManager.propTypes = {
	widgetId: PropTypes.oneOfType([PropTypes.string, PropTypes.number]).isRequired,
};
