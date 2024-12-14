<?php declare(strict_types=1); namespace Andromeda\Core; require_once("init.php");

class CryptoTest extends \PHPUnit\Framework\TestCase
{
    public function testGenerateSalt() : void
    {
        $this->assertSame(16, Crypto::SaltLength());
        
        $salt = Crypto::GenerateSalt();
        $this->assertSame(16, strlen($salt));
    }

    public function testDeriveKey() : void
    {
        $password = "mypassword123";
        $salt = "0123456789ABCDEF"; // 16 bytes!

        $this->assertSame(
            "6bf2e7a99d16a81842bf694fc6aae064",
            bin2hex(Crypto::DeriveKey($password, $salt, 16)));
            
        $this->assertSame(
            "7fcaae0ab5247d30e8eba8809455d3c3",
            bin2hex(Crypto::DeriveKey($password, $salt, 16, true)));
            
        $this->assertSame(
            "0d347291ce9eca4b88e9be36af8a05aeac624c724bd17f5d",
            bin2hex(Crypto::DeriveKey($password, $salt, 24)));
        
        $this->assertSame(
            "65fcdc118a5a9e49f134a703b66539fa5b494a2e3299c102",
            bin2hex(Crypto::DeriveKey($password, $salt, 24, true)));
            
        $this->assertSame(
            "a49dd97a617acdc03bbd4f3003b9d5d494c7ff69bd22218495e6dde729f6f11f",
            bin2hex(Crypto::DeriveKey($password, $salt, 32)));
        
        $this->assertSame(
            "1f291553177127a71e6e1597c75c098889dd5cf7bf08361214767cab1b358bae",
            bin2hex(Crypto::DeriveKey($password, $salt, 32, true)));
    }

    public function testCryptoSecret() : void
    {
        $this->assertSame(32, Crypto::SecretKeyLength());
        $this->assertSame(24, Crypto::SecretNonceLength());
        $this->assertSame(16, Crypto::SecretOutputOverhead());
        
        $this->assertSame(32, strlen(Crypto::GenerateSecretKey()));
        $this->assertSame(24, strlen(Crypto::GenerateSecretNonce()));
        
        $key = "0123456789ABCDEF0123456789ABCDEF"; // 32 bytes
        $nonce = "0123456789ABCDEF01234567"; // 24 bytes
        
        $msg = "my super secret data...";
                
        $this->assertSame(
            "5b394ad117f06e2622974cb5792d1ad41016613a37d9730b54fb214aae80efcd9218c03f0cc81e", 
            bin2hex($enc = Crypto::EncryptSecret($msg, $nonce, $key)));
        $this->assertSame(strlen($msg)+Crypto::SecretOutputOverhead(), strlen($enc));
        
        $this->assertSame($msg, Crypto::DecryptSecret($enc, $nonce, $key));
        
        $extra = "extra auth data...";
        
        $this->assertSame(
            "5b394ad117f06e2622974cb5792d1ad41016613a37d973ff34324579b2d4bd0f815a507afc5215",
            bin2hex($enc = Crypto::EncryptSecret($msg, $nonce, $key, $extra)));
        $this->assertSame(strlen($msg)+Crypto::SecretOutputOverhead(), strlen($enc));
        
        $this->assertSame($msg, Crypto::DecryptSecret($enc, $nonce, $key, $extra));
        
        $this->expectException(Exceptions\DecryptionFailedException::class);
        $badkey = "1113456789ABCDEF0123456789ABCDEF"; // 32 bytes
        Crypto::DecryptSecret($enc, $nonce, $badkey, $extra);
    }
    
    public function testCryptoPublic() : void
    {
        $this->assertSame(24, Crypto::PublicNonceLength());
        $this->assertSame(16, Crypto::PublicOutputOverhead());
        $this->assertSame(24, strlen(Crypto::GeneratePublicNonce()));
        
        $pair = Crypto::GeneratePublicKeyPair();   
        $this->assertSame(32, strlen($pair['public']));
        $this->assertSame(32, strlen($pair['private']));
        
        $pub1 = (string)hex2bin("3dc676268fc36f9fe45b065186390adbcd88fadcb8f9e6384da5dd362a80ac2d");
        $priv1 = (string)hex2bin("292053109b4f6f89ebbde27771dc4b40bdddffb7c420064cd86d1e805f2659a4");
        
        $pub2 = (string)hex2bin("62d8459524005df5f4db734453e8069926b5631435545a81df45852f032d1447");
        $priv2 = (string)hex2bin("cf05a70e088db92bf3a0ee12e2e85f9f6f73bea2d73ab3c3d90c789123f3d3e0");
        
        $msg = "my super secret data...";
        $nonce = "0123456789ABCDEF01234567"; // 24 bytes
        
        $this->assertSame(
            "ff05ee407b1f91c764e112f9c59a9716055a62d0022dd931c57f50fd3c11dfca559adaba70b73e",
            bin2hex($enc = Crypto::EncryptPublic($msg, $nonce, $priv1, $pub2)));
        $this->assertSame(strlen($msg)+Crypto::PublicOutputOverhead(), strlen($enc));
        
        $this->assertSame($msg, Crypto::DecryptPublic($enc, $nonce, $priv2, $pub1));
        
        $this->expectException(Exceptions\DecryptionFailedException::class);
        Crypto::DecryptPublic($enc, $nonce, $priv1, $pub1);
    }
    
    public function testCryptoAuth() : void
    {
        $this->assertSame(32, Crypto::AuthKeyLength());  
        $this->assertSame(32, Crypto::AuthTagLength());
        $this->assertSame(32, strlen(Crypto::GenerateAuthKey()));
        
        $msg = "this should be authenticated...";
        $key = "0123456789ABCDEF0123456789ABCDEF"; // 32 bytes
        
        $this->assertSame(
            "c4d6a1077b910511bb3f9af766e469726d3e461783f48cf156e8d9966434dbc4",
            bin2hex($mac = Crypto::MakeAuthCode($msg, $key)));
        
        $this->assertTrue(Crypto::TryCheckAuthCode($mac, $msg, $key));
        Crypto::CheckAuthCode($mac, $msg, $key); // no throw
        
        $badmac = (string)hex2bin("abc6a1077b910511bb3f9af766e469726d3e461783f48cf156e8d9966434dbc4");
        $this->assertFalse(Crypto::TryCheckAuthCode($badmac, $msg, $key));
        
        $this->expectException(Exceptions\DecryptionFailedException::class);
        Crypto::CheckAuthCode($badmac, $msg, $key);
    }
}
