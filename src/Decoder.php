<?php declare(strict_types=1);
namespace BitTorrent;

use InvalidArgumentException;

class Decoder implements DecoderInterface {
    private $encoder;

    public function __construct(EncoderInterface $encoder = null) {
        if ($encoder === null) {
            $encoder = new Encoder();
        }

        $this->encoder = $encoder;
    }

    public function decodeFile(string $file, bool $strict = false) : array {
        if (!is_readable($file)) {
            throw new InvalidArgumentException('File ' . $file . ' does not exist or can not be read.');
        }

        $dictionary = $this->decodeDictionary(file_get_contents($file, true));

        if ($strict) {
            if (!isset($dictionary['announce']) || !is_string($dictionary['announce']) && !empty($dictionary['announce'])) {
                throw new InvalidArgumentException('Missing or empty "announce" key.');
            } else if (!isset($dictionary['info']) || !is_array($dictionary['info']) && !empty($dictionary['info'])) {
                throw new InvalidArgumentException('Missing or empty "info" key.');
            }
        }

        return $dictionary;
    }

    public function decode(string $string) {
        if ($string[0] === 'i') {
            return $this->decodeInteger($string);
        } else if ($string[0] === 'l') {
            return $this->decodeList($string);
        } else if ($string[0] === 'd') {
            return $this->decodeDictionary($string);
        } else if (preg_match('/^\d+:/', $string)) {
            return $this->decodeString($string);
        }

        throw new InvalidArgumentException('Parameter is not correctly encoded.');
    }

    public function decodeInteger(string $integer) : int {
        if ($integer[0] !== 'i' || (!$ePos = strpos($integer, 'e'))) {
            throw new InvalidArgumentException('Invalid integer. Integers must start wth "i" and end with "e".');
        }

        $integer = substr($integer, 1, ($ePos - 1));
        $len = strlen($integer);

        if (($integer[0] === '0' && $len > 1) || ($integer[0] === '-' && $integer[1] === '0') || !is_numeric($integer)) {
            throw new InvalidArgumentException('Invalid integer value.');
        }

        return (int) $integer;
    }

    public function decodeString(string $string) : string {
        $stringParts = explode(':', $string, 2);

        if (count($stringParts) !== 2) {
            throw new InvalidArgumentException('Invalid string. Strings consist of two parts separated by ":".');
        }

        $length = (int) $stringParts[0];
        $lengthLen = strlen((string) $length);

        if (($lengthLen + 1 + $length) > strlen($string)) {
            throw new InvalidArgumentException('The length of the string does not match the prefix of the encoded data.');
        }

        return substr($string, ($lengthLen + 1), $length);
    }

    public function decodeList(string $list) : array {
        if ($list[0] !== 'l') {
            throw new InvalidArgumentException('Parameter is not an encoded list.');
        }

        $ret = [];

        $length = strlen($list);
        $i = 1;

        while ($i < $length) {
            if ($list[$i] === 'e') {
                break;
            }

            $part = substr($list, $i);
            $decodedPart = $this->decode($part);
            $ret[] = $decodedPart;
            $i += strlen($this->encoder->encode($decodedPart));
        }

        return $ret;
    }

    public function decodeDictionary(string $dictionary) : array {
        if ($dictionary[0] !== 'd') {
            throw new InvalidArgumentException('Parameter is not an encoded dictionary.');
        }

        $length = strlen($dictionary);
        $ret = [];
        $i = 1;

        while ($i < $length) {
            if ($dictionary[$i] === 'e') {
                break;
            }

            $keyPart = substr($dictionary, $i);
            $key = $this->decodeString($keyPart);
            $keyPartLength = strlen($this->encoder->encodeString($key));

            $valuePart = substr($dictionary, ($i + $keyPartLength));
            $value = $this->decode($valuePart);
            $valuePartLength = strlen($this->encoder->encode($value));

            $ret[$key] = $value;
            $i += ($keyPartLength + $valuePartLength);
        }

        return $ret;
    }
}
