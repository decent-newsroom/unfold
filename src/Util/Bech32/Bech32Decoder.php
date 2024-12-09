<?php

namespace App\Util\Bech32;

use Exception;

class Bech32Decoder
{
    // Function to decode Bech32 without character length restriction
    /**
     * @throws Exception
     */
    public function decodeBech32(string $bech32): array
    {
        $charset = 'qpzry9x8gf2tvdw0s3jn54khce6mua7l';

        // Find the separator (1)
        $pos = strrpos($bech32, '1');
        if ($pos === false) {
            throw new Exception('Invalid Bech32 string');
        }

        // Extract human-readable part (HRP)
        $hrp = substr($bech32, 0, $pos);

        // Extract data part
        $data_part = substr($bech32, $pos + 1);
        $data = [];
        for ($i = 0; $i < strlen($data_part); $i++) {
            $data[] = strpos($charset, $data_part[$i]);
            if ($data[$i] === false) {
                throw new Exception('Invalid character in Bech32 string');
            }
        }

        return [$hrp, $data];
    }

    // Function to convert 5-bit data to 8-bit data
    public function convertBits(array $data, int $fromBits, int $toBits, bool $pad = true): array
    {
        $acc = 0;
        $bits = 0;
        $ret = [];
        $maxv = (1 << $toBits) - 1;
        $max_acc = (1 << ($fromBits + $toBits - 1)) - 1;

        foreach ($data as $value) {
            if ($value < 0 || $value >> $fromBits) {
                throw new Exception('Invalid value in data');
            }
            $acc = (($acc << $fromBits) | $value) & $max_acc;
            $bits += $fromBits;
            while ($bits >= $toBits) {
                $bits -= $toBits;
                $ret[] = ($acc >> $bits) & $maxv;
            }
        }

        if ($pad) {
            if ($bits > 0) {
                $ret[] = ($acc << ($toBits - $bits)) & $maxv;
            }
        } else if ($bits >= $fromBits || (($acc << ($toBits - $bits)) & $maxv)) {
            throw new Exception('Invalid padding');
        }

        return $ret;
    }

    // Public method to decode a Nostr Bech32 string and return hex
    public function decodeNostrBech32ToHex(string $bech32): string
    {
        list($hrp, $data) = $this->decodeBech32($bech32);

        // Convert 5-bit data back to 8-bit data
        $decodedData = $this->convertBits($data, 5, 8, false);

        // Return the decoded data as a hex string
        return bin2hex(pack('C*', ...$decodedData));
    }

    // Public method to decode a Nostr Bech32 string and return the binary data

    /**
     * @throws Exception
     */
    public function decodeNostrBech32ToBinary(string $bech32): array
    {
        list($hrp, $data) = $this->decodeBech32($bech32);

        // Convert 5-bit data to 8-bit data
        $decodedData = $this->convertBits($data, 5, 8);

        return [$hrp, $decodedData];
    }

    // Public method to parse the binary data into TLV format
    public function parseTLV(array $binaryData): array
    {
        $parsedTLVs = [];
        $offset = 0;

        while ($offset < count($binaryData)) {
            if ($offset + 1 >= count($binaryData)) {
                throw new Exception("Incomplete TLV data");
            }

            // Read the Type (T) and Length (L)
            $type = $binaryData[$offset];
            $length = $binaryData[$offset + 1];
            $offset += 2;

            // Ensure we have enough data for the value
            if ($offset + $length > count($binaryData)) {
                break;
            } else {
                // Extract the Value (V)
                $value = array_slice($binaryData, $offset, $length);
            }

            $offset += $length;

            // Add the TLV to the parsed array
            $parsedTLVs[] = [
                'type' => $type,
                'length' => $length,
                'value' => $value,
            ];
        }

        return $parsedTLVs;
    }

    // Decode and parse a Bech32 string

    /**
     * @throws Exception
     */
    public function decodeAndParseNostrBech32(string $bech32): array
    {
        // Step 1: Decode Bech32 to binary data
        list($hrp, $binaryData) = $this->decodeNostrBech32ToBinary($bech32);

        if ($hrp == 'npub') {
            return [$hrp, $binaryData];
        }

        // Step 2: Parse the binary data into TLV format
        $tlvData = $this->parseTLV($binaryData);

        return [$hrp, $tlvData];
    }
}
