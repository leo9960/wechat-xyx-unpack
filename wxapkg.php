<?php

function unpack_wxapkg($file, $targetDir){
    if (!is_dir($targetDir)){
        mkdir($targetDir);
    }
    $file = file_get_contents($file);
    $ptr = 18;

    $headerStruct = new StructDef([
        'mask1' => 'ushort',
        'info1' => 'ulong',
        'indexInfoLength' => 'ulong',
        'bodyInfoLength' => 'ushort',
        'mask2' => 'ushort',
        'fileCount' => 'ulong',
    ]);
    $header = $headerStruct->unpack($file);

    $unpackULong = function () use (&$file, &$ptr) {
        $ret = unpack_ulong(substr($file, $ptr, 4));
        $ptr += 4;
        return $ret;
    };

    $unpackUShort = function () use (&$file, &$ptr) {
        $ret = unpack_ushort(substr($file, $ptr, 2));
        $ptr += 2;
        return $ret;
    };

    $unpackStr = function ($len) use (&$file, &$ptr) {
        $ret = substr($file, $ptr, $len);
        $ptr += $len;
        return $ret;
    };
	
    $fileCount = $header['fileCount'];
    $unpackedFiles = [];

    for ($i = 0; $i < $fileCount; $i++) {
        $nameLength = $unpackULong();
        $f = [
            'nameLength' => $nameLength,
            'name' => $unpackStr($nameLength),
            'offset' => $unpackULong(),
            'size' => $unpackULong(),
        ];

        $f['content'] = substr($file, $f['offset'], $f['size']);
        $unpackedFiles[] = $f;

        $destFile = $targetDir . $f['name'];
        $destDir = dirname($destFile);
        if (!is_dir($destDir)){
            mkdir($destDir, 0777, true);
        }
		$f['name'] = mb_convert_encoding($f['name'],"GBK","UTF-8");
        file_put_contents($targetDir . $f['name'], $f['content']);
    }
}

function unpack_ulong($str)
{
    $x = unpack('N', $str);
    return $x[1];
}

function unpack_ushort($str)
{
    $x = unpack('n', $str);
    return $x[1];
}

class StructDef
{
    protected $def;
    protected $unpackFormat;

    public function __construct($def)
    {
        $this->def = $def;
        $this->unpackFormat = self::convertStructDefToUnpackFormat($def);
    }

    public function unpack($data)
    {
        return unpack($this->unpackFormat, $data);
    }

    protected static function convertStructDefToUnpackFormat($def)
    {
        $defTypeToUnpackType = [
            'byte' => 'C',
            'uchar' => 'C',
            'u8' => 'C',
            'ushort' => 'n',
            'u16' => 'n',
            'ulong' => 'N',
            'u32' => 'N',
        ];

        $ret = [];
        foreach ($def as $key => $type) {
            $ret[] = $defTypeToUnpackType[$type] . $key;
        }

        return implode('/', $ret);
    }
}