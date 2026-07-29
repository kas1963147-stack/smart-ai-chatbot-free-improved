/**
 * Bulk Selection Hook
 *
 * Custom hook for managing bulk selection in lists.
 * Provides select all, select individual, and selection state management.
 *
 * @version 1.0.0
 * @package
 */
import { useState, useCallback, useMemo } from '@wordpress/element';

/**
 * useBulkSelection hook
 *
 * @param {Array} items - Array of items with unique 'id' property
 * @return {Object} Selection state and handlers
 */
export default function useBulkSelection( items = [] ) {
	const [ selectedIds, setSelectedIds ] = useState( new Set() );

	// Get all item IDs
	const allIds = useMemo( () => {
		return new Set( items.map( ( item ) => item.id ) );
	}, [ items ] );

	// Check if all items are selected
	const isAllSelected = useMemo( () => {
		return items.length > 0 && selectedIds.size === items.length;
	}, [ selectedIds, items.length ] );

	// Check if some items are selected (for indeterminate checkbox)
	const isSomeSelected = useMemo( () => {
		return selectedIds.size > 0 && selectedIds.size < items.length;
	}, [ selectedIds, items.length ] );

	// Toggle selection of a single item
	const toggleSelect = useCallback( ( id ) => {
		setSelectedIds( ( prev ) => {
			const next = new Set( prev );
			if ( next.has( id ) ) {
				next.delete( id );
			} else {
				next.add( id );
			}
			return next;
		} );
	}, [] );

	// Select a single item
	const select = useCallback( ( id ) => {
		setSelectedIds( ( prev ) => new Set( prev ).add( id ) );
	}, [] );

	// Deselect a single item
	const deselect = useCallback( ( id ) => {
		setSelectedIds( ( prev ) => {
			const next = new Set( prev );
			next.delete( id );
			return next;
		} );
	}, [] );

	// Toggle select all
	const toggleSelectAll = useCallback( () => {
		if ( isAllSelected ) {
			setSelectedIds( new Set() );
		} else {
			setSelectedIds( new Set( allIds ) );
		}
	}, [ isAllSelected, allIds ] );

	// Select all
	const selectAll = useCallback( () => {
		setSelectedIds( new Set( allIds ) );
	}, [ allIds ] );

	// Clear all selections
	const clearSelection = useCallback( () => {
		setSelectedIds( new Set() );
	}, [] );

	// Check if a specific item is selected
	const isSelected = useCallback(
		( id ) => {
			return selectedIds.has( id );
		},
		[ selectedIds ]
	);

	// Get selected items
	const selectedItems = useMemo( () => {
		return items.filter( ( item ) => selectedIds.has( item.id ) );
	}, [ items, selectedIds ] );

	return {
		selectedIds,
		selectedCount: selectedIds.size,
		selectedItems,
		isAllSelected,
		isSomeSelected,
		toggleSelect,
		select,
		deselect,
		toggleSelectAll,
		selectAll,
		clearSelection,
		isSelected,
	};
}
