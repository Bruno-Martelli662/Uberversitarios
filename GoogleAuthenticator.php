<?php
class GoogleAuthenticator {
    private $codeLength = 6;

    public function createSecret($secretLength = 16) {
        $validChars = $this->getBase32LookupTable();
        unset($validChars[32]);
        
        $secret = '';
        for ($i = 0; $i < $secretLength; $i++) {
            $secret .= $validChars[array_rand($validChars)];
        }
        return $secret;
    }

    public function getQRCodeGoogleUrl($name, $secret, $title = null) {
        return 'https://chart.googleapis.com/chart?chs=200x200&chld=M|0&cht=qr&chl=' . 
               urlencode('otpauth://totp/' . $name . '?secret=' . $secret . '&issuer=' . urlencode($title));
    }

    public function verifyCode($secret, $code, $discrepancy = 1) {
        $currentTime = floor(time() / 30);
        
        for ($i = -$discrepancy; $i <= $discrepancy; $i++) {
            $calculatedCode = $this->getCode($secret, $currentTime + $i);
            if ($calculatedCode == $code) {
                return true;
            }
        }
        
        return false;
    }

    private function getBase32LookupTable() {
        return array(
            'A', 'B', 'C', 'D', 'E', 'F', 'G', 'H', 'I', 'J', 'K', 'L', 'M', 
            'N', 'O', 'P', 'Q', 'R', 'S', 'T', 'U', 'V', 'W', 'X', 'Y', 'Z',
            '2', '3', '4', '5', '6', '7', '='
        );
    }

    private function getCode($secret, $timeSlice) {
        $secretkey = $this->base32Decode($secret);
        $time = chr(0).chr(0).chr(0).chr(0).pack('N*', $timeSlice);
        $hm = hash_hmac('SHA1', $time, $secretkey, true);
        $offset = ord(substr($hm, -1)) & 0x0F;
        $hashpart = substr($hm, $offset, 4);
        $value = unpack('N', $hashpart);
        $value = $value[1];
        $value = $value & 0x7FFFFFFF;
        $modulo = pow(10, $this->codeLength);
        return str_pad($value % $modulo, $this->codeLength, '0', STR_PAD_LEFT);
    }

    private function base32Decode($secret) {
        $lut = $this->getBase32LookupTable();
        $lut = array_flip($lut);
        $buffer = '';
        
        for ($i = 0; $i < strlen($secret); $i++) {
            $c = $secret[$i];
            if (isset($lut[$c])) {
                $buffer .= str_pad(decbin($lut[$c]), 5, '0', STR_PAD_LEFT);
            }
        }
        
        $result = '';
        for ($i = 0; $i + 8 <= strlen($buffer); $i += 8) {
            $result .= chr(bindec(substr($buffer, $i, 8)));
        }
        return $result;
    }
}
?>