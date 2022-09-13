<?php declare(strict_types=1); namespace Andromeda\Core; if (!defined('Andromeda')) die();

if (!function_exists('sodium_memzero')) 
    die("PHP Sodium Extension Required".PHP_EOL);

require_once(ROOT."/Core/Exceptions.php");

/** libsodium wrapper class for crypto */
class Crypto
{
    /** Generates a salt for use with DeriveKey() */
    public static function GenerateSalt() : string
    {
        return random_bytes(SODIUM_CRYPTO_PWHASH_SALTBYTES);
    }
    
    private const FAST_OPS = 1;
    private const FAST_MEMORY = 16*1024;
    
    /**
     * Generates an encryption key from a password 
     * @param string $password the password to derive the key from
     * @param string $salt a generated salt to use
     * @param int $bytes the number of bytes required to output
     * @param bool $fast if true, does a very fast transformation (use only if the password is itself a key)
     * @return string the derived binary key
     */
    public static function DeriveKey(string $password, string $salt, int $bytes, bool $fast = false) : string
    {
        $key = sodium_crypto_pwhash(
            $bytes, $password, $salt,
            $fast ? self::FAST_OPS : SODIUM_CRYPTO_PWHASH_OPSLIMIT_INTERACTIVE,
            $fast ? self::FAST_MEMORY : SODIUM_CRYPTO_PWHASH_MEMLIMIT_INTERACTIVE,
            SODIUM_CRYPTO_PWHASH_ALG_ARGON2ID13 );
        sodium_memzero($password); return $key;
    }

    /** 
     * Returns the length of a key for use with this class 
     * @return positive-int
     */
    public static function SecretKeyLength() : int { return SODIUM_CRYPTO_AEAD_XCHACHA20POLY1305_IETF_KEYBYTES; }
    
    /** 
     * Returns the length of a nonce for use with this class 
     * @return positive-int
     */
    public static function SecretNonceLength() : int { return SODIUM_CRYPTO_AEAD_XCHACHA20POLY1305_IETF_NPUBBYTES; }
    
    /** 
     * Returns the size overhead of an encrypted string over a plaintext one
     * 
     * The size overhead exists because the crypto is authenticated
     */
    public static function SecretOutputOverhead() : int { return SODIUM_CRYPTO_AEAD_XCHACHA20POLY1305_IETF_ABYTES; }
    
    /** Generates a crypto key for use with this class */
    public static function GenerateSecretKey() : string { return random_bytes(static::SecretKeyLength()); }
    
    /** Generates a crypto nonce for use with this class */
    public static function GenerateSecretNonce() : string { return random_bytes(static::SecretNonceLength()); }
    
    /**
     * Encrypts the given data
     * @param string $data the plaintext to encrypt
     * @param string $nonce the crypto nonce to use
     * @param string $key the crypto key to use
     * @param string $extra extra data to include in authentication (must be provided at decrypt)
     * @return string an encrypted and authenticated ciphertext
     * @see sodium_crypto_aead_xchacha20poly1305_ietf_encrypt()
     */
    public static function EncryptSecret(string $data, string $nonce, string $key, string $extra = "") : string
    {
        $output = sodium_crypto_aead_xchacha20poly1305_ietf_encrypt($data, $extra, $nonce, $key);
        sodium_memzero($data); sodium_memzero($key);
        return $output;
    }
    
    /**
     * Decrypts the given data
     * @param string $data the ciphertext to decrypt
     * @param string $nonce the nonce that was used to encrypt
     * @param string $key the key that was used to encrypt
     * @param string $extra the extra auth data used to encrypt
     * @throws DecryptionFailedException if decryption fails
     * @return string the decrypted and authenticated plaintext
     * @see sodium_crypto_aead_xchacha20poly1305_ietf_decrypt()
     */
    public static function DecryptSecret(string $data, string $nonce, string $key, string $extra = "") : string
    {
        $output = sodium_crypto_aead_xchacha20poly1305_ietf_decrypt($data, $extra, $nonce, $key);
        sodium_memzero($data); sodium_memzero($key);
        if ($output === false) throw new DecryptionFailedException();
        return $output;
    }

    /** 
     * Returns the length of a nonce for use with this class 
     * @return positive-int
     */
    public static function PublicNonceLength() : int { return SODIUM_CRYPTO_BOX_NONCEBYTES; }

    /** Generates a crypto nonce for use with this class */
    public static function GeneratePublicNonce() : string { return random_bytes(static::PublicNonceLength()); }
    
    /**
     * Generates a public/private keypair
     * @return array{public:string,private:string}
     */
    public static function GeneratePublicKeyPair() : array
    {
        $keypair = sodium_crypto_box_keypair();
        
        return array(
            'public' => sodium_crypto_box_publickey($keypair),
            'private' => sodium_crypto_box_secretkey($keypair)
        );
    }
    
    /**
     * Encrypts and signs data from a sender to a recipient
     * @param string $message the plaintext to encrypt
     * @param string $nonce the nonce to encrypt with
     * @param string $sender_private the sender's private key
     * @param string $recipient_public the recipient's public key
     * @return string the encrypted and signed ciphertext
     */
    public static function EncryptPublic(string $message, string $nonce, string $sender_private, string $recipient_public)
    {
        $keypair = sodium_crypto_box_keypair_from_secretkey_and_publickey($sender_private, $recipient_public);
        $output = sodium_crypto_box($message, $nonce, $keypair);
        sodium_memzero($message); sodium_memzero($sender_private);
        return $output;
    }
    
    /**
     * Decrypts and verifies data from a sender to a recipient
     * @param string $message the ciphertext to decrypt
     * @param string $nonce the nonce that was used to encrypt
     * @param string $recipient_private the recipient's private key
     * @param string $sender_public the sender's public key
     * @throws DecryptionFailedException if decryption fails
     * @return string the decrypted and verified plaintext
     */
    public static function DecryptPublic(string $message, string $nonce, string $recipient_private, string $sender_public)
    {
        $keypair = sodium_crypto_box_keypair_from_secretkey_and_publickey($recipient_private, $sender_public);
        $output = sodium_crypto_box_open($message, $nonce, $keypair);
        sodium_memzero($message); sodium_memzero($recipient_private);
        if ($output === false) throw new DecryptionFailedException();
        return $output;
    }

    /** 
     * Returns the length of a key for use with this class 
     * @return positive-int
     */
    public static function AuthKeyLength() : int { return SODIUM_CRYPTO_AUTH_KEYBYTES; }
    
    /** 
     * Returns the length of the authentication tag generated 
     * @return positive-int
     */
    public static function AuthTagLength() : int { return SODIUM_CRYPTO_AUTH_BYTES; }
    
    /** Generates a crypto key for use with this class */
    public static function GenerateAuthKey() : string { return random_bytes(static::AuthKeyLength()); }
    
    /**
     * Creates an authentication code (MAC) from a message and key
     * @param string $message the message to create the MAC for
     * @param string $key the secret key to use for creating the MAC
     * @return string the message authentication code (MAC)
     */
    public static function MakeAuthCode(string $message, string $key) : string
    {
        $output = sodium_crypto_auth($message, $key);
        sodium_memzero($message); sodium_memzero($key);
        return $output;
    }
    
    /**
     * Tries to authenticate a message using a secret key
     * @param string $mac the message authentication code
     * @param string $message the message to verify
     * @param string $key the key used in creating the MAC
     * @return bool true if authentication succeeds
     */
    public static function TryCheckAuthCode(string $mac, string $message, string $key) : bool
    {
        $output = sodium_crypto_auth_verify($mac, $message, $key);
        sodium_memzero($mac); sodium_memzero($message); sodium_memzero($key);
        return $output;
    }
    
    /**
     * Same as TryCheckAuthCode() but throws an exception on failure
     * @throws DecryptionFailedException if authentication fails
     * @see self::TryCheckAuthCode()
     */
    public static function CheckAuthCode(string $mac, string $message, string $key) : void
    {
        if (!static::TryCheckAuthCode($mac, $message, $key)) 
            throw new DecryptionFailedException();
    }
}
