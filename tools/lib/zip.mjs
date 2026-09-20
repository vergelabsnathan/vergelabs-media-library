/*
 *  A zip writer and a central-directory reader, in the repo, on purpose.
 *
 *  Lived in tools/deploy.mjs from 2026-08-31 (its header says why: PowerShell's
 *  Compress-Archive writes backslash entry names that Linux unzip turns into
 *  one unusable blob, and a packaging dependency contradicts a plugin whose
 *  argument is that it has no build step). Moved here on 2026-09-20 so the
 *  archive-hygiene check and the release re-cut read and write the same bytes
 *  the deploy does, rather than carrying a third copy each.
 *
 *  Deterministic: fixed timestamps, level-9 deflate, entries in the order
 *  given. Same files in, byte-identical archive out.
 */
import fs from 'node:fs';
import zlib from 'node:zlib';

export const crc32 = zlib.crc32
	? ( buf ) => zlib.crc32( buf ) >>> 0
	: ( () => {
		const table = new Uint32Array( 256 );
		for ( let i = 0; i < 256; i++ ) {
			let c = i;
			for ( let k = 0; k < 8; k++ ) {
				c = c & 1 ? 0xedb88320 ^ ( c >>> 1 ) : c >>> 1;
			}
			table[ i ] = c >>> 0;
		}
		return ( buf ) => {
			let c = 0xffffffff;
			for ( let i = 0; i < buf.length; i++ ) {
				c = table[ ( c ^ buf[ i ] ) & 0xff ] ^ ( c >>> 8 );
			}
			return ( c ^ 0xffffffff ) >>> 0;
		};
	} )();


/**
 *  Write a zip. `entries` is [ name, Buffer ] with names already using the
 *  forward slashes the format actually specifies.
 *
 *  Timestamps are fixed rather than taken from the clock, so two runs over
 *  unchanged files produce the same bytes and a pointless commit is visible as
 *  no diff at all.
 */
export function writeZip( file, entries ) {

	const chunks = [];
	const central = [];
	let offset = 0;

	for ( const [ name, body ] of entries ) {

		const nameBuf = Buffer.from( name, 'utf8' );
		const deflated = zlib.deflateRawSync( body, { level: 9 } );
		const store = deflated.length >= body.length;
		const data = store ? body : deflated;
		const sum = crc32( body );

		const local = Buffer.alloc( 30 );
		local.writeUInt32LE( 0x04034b50, 0 );
		local.writeUInt16LE( 20, 4 );               // version needed
		local.writeUInt16LE( 0x0800, 6 );           // UTF-8 names
		local.writeUInt16LE( store ? 0 : 8, 8 );    // stored or deflated
		local.writeUInt16LE( 0, 10 );               // time
		local.writeUInt16LE( 0x0021, 12 );          // date: 1980-01-01
		local.writeUInt32LE( sum, 14 );
		local.writeUInt32LE( data.length, 18 );
		local.writeUInt32LE( body.length, 22 );
		local.writeUInt16LE( nameBuf.length, 26 );
		local.writeUInt16LE( 0, 28 );

		chunks.push( local, nameBuf, data );

		const dir = Buffer.alloc( 46 );
		dir.writeUInt32LE( 0x02014b50, 0 );
		dir.writeUInt16LE( 20, 4 );
		dir.writeUInt16LE( 20, 6 );
		dir.writeUInt16LE( 0x0800, 8 );
		dir.writeUInt16LE( store ? 0 : 8, 10 );
		dir.writeUInt16LE( 0, 12 );
		dir.writeUInt16LE( 0x0021, 14 );
		dir.writeUInt32LE( sum, 16 );
		dir.writeUInt32LE( data.length, 20 );
		dir.writeUInt32LE( body.length, 24 );
		dir.writeUInt16LE( nameBuf.length, 28 );
		dir.writeUInt16LE( 0, 30 );
		dir.writeUInt16LE( 0, 32 );
		dir.writeUInt16LE( 0, 34 );
		dir.writeUInt16LE( 0, 36 );
		dir.writeUInt32LE( 0, 38 );                 // external attrs
		dir.writeUInt32LE( offset, 42 );

		central.push( Buffer.concat( [ dir, nameBuf ] ) );
		offset += local.length + nameBuf.length + data.length;
	}

	const dirBuf = Buffer.concat( central );

	const end = Buffer.alloc( 22 );
	end.writeUInt32LE( 0x06054b50, 0 );
	end.writeUInt16LE( entries.length, 8 );
	end.writeUInt16LE( entries.length, 10 );
	end.writeUInt32LE( dirBuf.length, 12 );
	end.writeUInt32LE( offset, 16 );

	fs.writeFileSync( file, Buffer.concat( [ ...chunks, dirBuf, end ] ) );
}


/**
 *  The end-of-central-directory record: how many entries and where the
 *  directory starts, or null when there is none within the last 64KB (the
 *  most a trailing comment can add). One rule for both readers below.
 */
function endRecord( buf ) {
	for ( let i = buf.length - 22; i >= 0 && i > buf.length - 66000; i-- ) {
		if ( buf.readUInt32LE( i ) === 0x06054b50 ) {
			return { count: buf.readUInt16LE( i + 10 ), at: buf.readUInt32LE( i + 16 ) };
		}
	}
	return null;
}


/**
 *  Read back the central directory: name and CRC per entry, as a Map, or null
 *  when the file is missing or is not a zip.
 *
 *  Enough to prove an archive holds what was put in it without unpacking it
 *  anywhere.
 */
export function readZipIndex( file ) {

	if ( ! fs.existsSync( file ) ) {
		return null;
	}

	const buf = fs.readFileSync( file );
	const end = endRecord( buf );
	if ( ! end ) {
		return null;
	}

	const count = end.count;
	let at = end.at;
	const out = new Map();

	for ( let i = 0; i < count; i++ ) {
		if ( buf.readUInt32LE( at ) !== 0x02014b50 ) {
			return null;
		}
		const sum = buf.readUInt32LE( at + 16 );
		const nameLen = buf.readUInt16LE( at + 28 );
		const extraLen = buf.readUInt16LE( at + 30 );
		const commentLen = buf.readUInt16LE( at + 32 );
		out.set( buf.toString( 'utf8', at + 46, at + 46 + nameLen ), sum >>> 0 );
		at += 46 + nameLen + extraLen + commentLen;
	}

	return out;
}


/**
 *  Every entry of a zip, unpacked in memory: [ name, Buffer ] in central
 *  directory order, directory entries included with an empty body. Stored and
 *  deflated entries only, which is all this repo's archives and WordPress's
 *  own ever use.
 */
export function readZipEntries( file ) {

	const buf = fs.readFileSync( file );
	const end = endRecord( buf );
	if ( ! end ) {
		throw new Error( `${ file } is not a zip` );
	}

	const count = end.count;
	let at = end.at;
	const out = [];

	for ( let i = 0; i < count; i++ ) {
		const method = buf.readUInt16LE( at + 10 );
		const size = buf.readUInt32LE( at + 20 );
		const nameLen = buf.readUInt16LE( at + 28 );
		const extraLen = buf.readUInt16LE( at + 30 );
		const commentLen = buf.readUInt16LE( at + 32 );
		const local = buf.readUInt32LE( at + 42 );
		const name = buf.toString( 'utf8', at + 46, at + 46 + nameLen );

		const localNameLen = buf.readUInt16LE( local + 26 );
		const localExtraLen = buf.readUInt16LE( local + 28 );
		const start = local + 30 + localNameLen + localExtraLen;
		const data = buf.subarray( start, start + size );

		if ( 0 === method ) {
			out.push( [ name, Buffer.from( data ) ] );
		} else if ( 8 === method ) {
			out.push( [ name, zlib.inflateRawSync( data ) ] );
		} else {
			throw new Error( `${ name }: compression method ${ method } is not one this reader knows` );
		}

		at += 46 + nameLen + extraLen + commentLen;
	}

	return out;
}
