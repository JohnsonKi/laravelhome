<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;

class TestCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'command:test {baseurl}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Command description';

    /**
     * Create a new command instance.
     *
     * @return void
     */
    public function __construct()
    {
        parent::__construct();
    }

    /**
     * Execute the console command.
     *
     * @return mixed
     */
    public function handle()
    {
        $baseurl = $this->argument("baseurl");
        $curl = curl_init($baseurl);
        curl_setopt($curl, CURLOPT_TIMEOUT, 180);
        curl_setopt($curl, CURLOPT_RETURNTRANSFER, TRUE);
        $data = curl_exec($curl);
        $error_number = curl_errno($curl);
        $error_message = curl_error($curl);
        curl_close($curl);
        if ($data === FALSE)
        {
            $this->error("無効なリンクURL[ $url ] [ $error_number ] [ $error_message ]");
        }

        $image = imagecreatefromstring($data);
        $width = imagesx($image);
        $height = imagesy($image);
        $this->info("OK [ $width ] [ $height ]");

        $suffixStr = explode(".", $baseurl);
        $suffixStr1 = exif_imagetype($baseurl);
        $suffixStr2 = image_type_to_extension($suffixStr1);
        $imageSuffix = image_type_to_extension(exif_imagetype($baseurl));
        $this->info("OK1 [ ". end($suffixStr)." ] [ $suffixStr1 ] [ $suffixStr2 ]");

        $imgname = './download/'. date("YmdHis") . rand(10, 99) . "." . end($suffixStr);
        imagejpeg($image, $imgname, 100);
    }
}
