<?php declare(strict_types=1);
namespace Packvium\Native;
final class NativePacker {
    /**
     * Each untyped model error's code and the prefix `PackError`'s `Display` puts before its
     * detail (`packvium-core` `error.rs`).
     */
    private const MODEL_ERROR_PREFIXES=[
        'invalid_input'=>'invalid input: ',
        'invalid_number'=>'invalid numeric value: ',
        'unsupported_unit'=>'unsupported unit: ',
    ];

    private ?\FFI $ffi;

    public function __construct(?string $libraryPath=null){
        $this->ffi=null;
        if($libraryPath===null||!extension_loaded('ffi')){return;}
        // Never let a missing library, an ABI mismatch or any other load-time
        // failure escape the constructor -- the bridge must always fall back to pure
        // PHP silently rather than crash the caller. `packvium_version()` is called
        // immediately as a health check because `FFI::cdef()` alone can succeed even
        // when the loaded symbols do not actually match this header (lazy resolution).
        try{
            $header='char *packvium_solve_json(const char *request_json); void packvium_free_string(char *value); char *packvium_version(void);';
            $ffi=\FFI::cdef($header,$libraryPath);
            $version=$ffi->packvium_version();
            if($version===null){throw new \RuntimeException('native library reported a null version');}
            try{$versionString=\FFI::string($version);}finally{$ffi->packvium_free_string($version);}
            if($versionString===''){throw new \RuntimeException('native library reported an empty version');}
            $this->ffi=$ffi;
        }catch(\Throwable $e){
            $this->ffi=null;
        }
    }

    public function backend():string{return $this->ffi?'rust':'php';}

    public function pack(array $request):array
    {
        $json=json_encode($request,JSON_THROW_ON_ERROR|JSON_PRESERVE_ZERO_FRACTION);
        $nativeFailure=null;
        if($this->ffi){
            try{
                $decoded=$this->nativeAnswer($json);
            }catch(\Throwable $error){
                $decoded=null;
                $nativeFailure=$error;
            }
            if($decoded!==null&&($decoded['status']??null)!=='error'){
                return $decoded;
            }
            if($decoded!==null){
                $refusal=self::refusalFor($decoded);
                // A refused request is a domain-level rejection, not a bridge
                // or FFI transport failure. Throw it directly so the native backend
                // reports invalid requests identically to pure PHP without disabling FFI.
                if($refusal!==null){throw $refusal;}
                // No code this bridge can name -- a panic caught at the FFI boundary, a
                // null or non-UTF-8 request, a solution that failed the core's own
                // validation -- is the library's failure, not the caller's.
                $nativeFailure=new \RuntimeException((string)($decoded['error']??'Native backend error'));
            }
            // A library can become unusable after its constructor health check (bad
            // return pointer, invalid JSON, runtime symbol failure). Disable it for every
            // subsequent call and retry the exact request through the reference PHP
            // implementation.
            $this->ffi=null;
        }

        if(!class_exists(\Packvium\Packer::class)){
            throw new \RuntimeException(
                'Install packvium/packvium or provide a healthy Rust library path',
                0,
                $nativeFailure
            );
        }
        return \Packvium\Serialization\ArrayCodec::pack($request);
    }

    /** The library's decoded answer to one request; any transport failure is thrown. */
    private function nativeAnswer(string $json):array
    {
        $ptr=$this->ffi->packvium_solve_json($json);
        if($ptr===null){throw new \RuntimeException('Native backend returned null');}
        try{$result=\FFI::string($ptr);}finally{$this->ffi->packvium_free_string($ptr);}
        $decoded=json_decode($result,true,512,JSON_THROW_ON_ERROR);
        if(!is_array($decoded)){
            throw new \RuntimeException('Native backend returned a non-object response');
        }
        return $decoded;
    }

    /**
     * The exception pure PHP throws for the request the core refused with `$payload`, or null
     * when the payload names no refusal this bridge can reproduce -- which the caller treats
     * as a native failure and answers from pure PHP instead.
     *
     * `invalid_request` and `invalid_fixed_placement` carry their reason, field and detail;
     * `unsupported_feature` keeps the core's own sentence; the core's untyped model errors are
     * what `ArrayCodec::pack` reports for a model it cannot build, `invalid_value` with no
     * field and the bare detail.
     *
     * @internal public so the bridge's own test can hold each code to its class without a
     *           library that fails on purpose.
     * @param array<string,mixed> $payload `packvium_solve_json`'s `{"status":"error", ...}`
     */
    public static function refusalFor(array $payload):?\Throwable
    {
        $code=$payload['code']??null;
        if(!is_string($code)){return null;}
        $message=(string)($payload['error']??'');
        if(!class_exists(\Packvium\Serialization\InvalidRequestException::class)){
            return new \RuntimeException($message);
        }
        if($code==='unsupported_feature'){
            return new \Packvium\Serialization\UnsupportedFeatureException($message);
        }
        if(isset(self::MODEL_ERROR_PREFIXES[$code])){
            return new \Packvium\Serialization\InvalidRequestException(
                'invalid_value','',self::withoutPrefix($message,self::MODEL_ERROR_PREFIXES[$code])
            );
        }
        $named=self::namedRefusal($payload);
        if($named===null){return null;}
        list($reason,$field,$detail)=$named;
        if($code==='invalid_request'){
            return new \Packvium\Serialization\InvalidRequestException($reason,$field,$detail);
        }
        if($code==='invalid_fixed_placement'){
            return new \Packvium\Validation\FixedPlacementException($detail,$reason,$field);
        }
        return null;
    }

    private static function withoutPrefix(string $message,string $prefix):string
    {
        return strncmp($message,$prefix,strlen($prefix))===0?substr($message,strlen($prefix)):$message;
    }

    /**
     * `[reason, field, detail]` when the payload carries all three as strings, else null.
     *
     * @param array<string,mixed> $payload
     * @return array{0:string,1:string,2:string}|null
     */
    private static function namedRefusal(array $payload):?array
    {
        $parts=[$payload['reason']??null,$payload['field']??null,$payload['detail']??null];
        foreach($parts as $part){
            if(!is_string($part)){return null;}
        }
        return $parts;
    }
}
