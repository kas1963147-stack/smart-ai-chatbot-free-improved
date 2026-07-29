import { Table as MantineTable } from '@mantine/core';
import PropTypes from 'prop-types';

export default function Table( { className = '', ...props } ) {
	const classes = [ 'swc-table', className ].filter( Boolean ).join( ' ' );
	return <MantineTable className={ classes } { ...props } />;
}

Table.propTypes = {
	className: PropTypes.string,
};

export const TableThead = MantineTable.Thead;
export const TableTbody = MantineTable.Tbody;
export const TableTfoot = MantineTable.Tfoot;
export const TableTr = MantineTable.Tr;
export const TableTh = MantineTable.Th;
export const TableTd = MantineTable.Td;
