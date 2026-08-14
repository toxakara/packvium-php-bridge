<?php declare(strict_types=1);
namespace Packvium\Native;
final class NativePacker {
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
                $ptr=$this->ffi->packvium_solve_json($json);
                if($ptr===null){throw new \RuntimeException('Native backend returned null');}
                try{$result=\FFI::string($ptr);}finally{$this->ffi->packvium_free_string($ptr);}
                $decoded=json_decode($result,true,512,JSON_THROW_ON_ERROR);
                if(!is_array($decoded)){
                    throw new \RuntimeException('Native backend returned a non-object response');
                }
                return $decoded;
            }catch(\Throwable $error){
                // A library can become unusable after its constructor health check
                // (bad return pointer, invalid JSON, runtime symbol failure). Disable
                // it for every subsequent call and retry the exact request through
                // the reference PHP implementation.
                $this->ffi=null;
                $nativeFailure=$error;
            }
        }

        if(!class_exists(\Packvium\Packer::class)){
            throw new \RuntimeException(
                'Install packvium/packvium 0.1.0 or provide a healthy Rust library path',
                0,
                $nativeFailure,
            );
        }
        return \Packvium\Serialization\ArrayCodec::pack($request);
    }
}
