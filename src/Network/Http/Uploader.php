<?php
namespace Zan\Framework\Network\Http;
/**
 * Class Uploader
 */
final class Uploader {
    //上传结果状态
    const STATE_SUCCESS = 0;//上传成功
    const STATE_NONE    = 1;//没有上传
    const STATE_ANY     = 2;//部分上传成功
    private $status = [
        STATE_SUCCESS => '上传成功',
        STATE_NONE    => '上传失败',
        STATE_ANY     => '部分文件上传成功',
    ];
    //上传错误码
    const ERR_NO_UPLOADED            = 1;//没有上传
    const ERR_NEED_UPLOAD_PATH       = 2;//必须指定上传路径
    const ERR_UPLOAD_PATH_NOT_EXIST  = 3;//上传路径不存在,且创建失败
    const ERR_UPLOAD_INVALIDS        = 4;//上传异常
    const ERR_COPY_TMP_FILE_FAILED   = 5;//临时文件拷贝失败
    const ERR_DELETE_TMP_FILE_FAILED = 6;//临时文件删除失败
    /**
     * @var array 上传配置
     */
    private $config;
    /**
     * @var array 上传的文件
     */
    private $files;
    private $result = [];//上传结果

    /**
     * Uploader constructor.
     * @param       $files
     * @param array $options
     */
    public function __construct($files, $config = []) {
        $this->files = $files;
        $this->config = array_merge([
            'path'         => './uploads', //默认上传路径
            'allowedTypes' => ['jpg', 'gif', 'png'],//默认上传类型
            'maxSize'      => 1000000, //默认上传大小限制(1m)
            'isRandNamed'  => TRUE, //是否开启 随机命名
        ], $config);
    }

    /**
     * 调用该方法上传文件
     * @return bool        如果上传成功返回数true
     */
    public function doUpload() {
        if (!$this->checkUploaded()) {
            return $this->result;
        }
        foreach ($this->files as $form_element => $file) {
            if (!$file || !is_array($file)) {
                //TODO:fixeed error
                continue;
            }
            $this->upload($file);
        }
        return $this->result;
    }

    /**
     * 上传检测
     */
    protected function checkUploaded() {
        //1.检测 files中是否存在文件
        if (!$this->files || !is_array($this->files)) {
            $this->setError(Uploader::ERR_NO_UPLOADED);
            return FALSE;
        }
        //2.检测 上传的路径及权限
        $config = $this->config;
        if (!isset($config['path']) || !$config['path']) {
            $this->setError(Uploader::ERR_NEED_UPLOAD_PATH);
            return FALSE;
        }
        if (!file_exists($config['path']) || !is_writable($config['path'])) {
            if (!mkdir($this->path, 0755, TRUE)) {
                $this->setError(Uploader::ERR_UPLOAD_PATH_NOT_EXIST);
                return FALSE;
            }
        }
        return TRUE;
    }

    /**
     * 设置错误信息
     * @param int $code
     */
    protected function setError($code) {
        static $messages = [
            Uploader::ERR_NO_UPLOADED           => '没有检测到上传内容',
            Uploader::ERR_NEED_UPLOAD_PATH      => '必须指定上传路径',
            Uploader::ERR_UPLOAD_PATH_NOT_EXIST => '上传路径不存在(自动创建失败,请手动创建并授权)',
        ];
        $msg = isset($messages[$code]) ? $message[$code] : '未知错误(' . $code . ')';
        $errors = isset($this->result['error']) ? $this->result['error'] : [];
        $error = isset($errors[$code]) ? $errors[$code] : NULL;
        if (!$error) {
            $errors[$code] = ['code' => $code, 'msg' => $msg];
            $this->result['error'] = $errors;
        }
    }

    /**
     * 上传文件
     * @param array  $file
     */
    protected function upload($file) {
        $config = $this->config;
        $names = $file['name'];
        $tmp_names = $file['tmp_name'];
        $sizes = $file['size'];
        $errors = $file['error'];
        $upload_errors = [];
        $is_check_extension = isset($config['allowedTypes']) && $config['allowedTypes'] ? TRUE : FALSE;
        for ($i = 0; $i < count($names); $i++) {
            $name = $names[$i];
            $tmp_name = $tmp_names[$i];
            $file_size = $sizes[$i];
            $error = $errors[$i];
            $extensions = explode('.', $name);
            $file_type = is_array($extensions) && $extensions ? strtolower(array_pop($extensions)) : '';
            //文件类型不匹配
            if ($is_check_extension && !in_array($file_type, $config['allowedTypes'])) {
                $upload_errors[$name] = sprintf('文件%s上传失败(不支持的文件格式)', $name);
                continue;
            }
            //文件过大
            if ($file_size > $config['maxSize']) {
                $upload_errors[$name] = vsprintf('文件%s上传失败,最多只允许上传%s大小的文件', [
                    $name,
                    $this->formatFileSize($config['maxSize']),
                ]);
                continue;
            }
            $file_info = new UploadFileInfo;
            $file_info->originName = $name;
            $file_info->size = $file_size;
            $file_info->sizeInfo = $this->formatFileSize($file_size);
            $file_info->extension = $file_type;
            $file_info->tmpName = $tmp_name;
            $this->copyFile($file_info, $upload_errors);
        }
        //上传失败
        if ($upload_errors) {
            $this->result['error'][Uploader::ERR_UPLOAD_INVALIDS] = $upload_errors;
        }
    }

    /**
     * 复制上传文件到指定的位置
     *
     * @param UploadFileInfo $fileInfo
     * @param array          $errors
     * @return bool
     */
    protected function copyFile($fileInfo, &$errors) {
        $config = $this->config;
        $path = rtrim($config['path'], '/') . '/';
        $new_file_name = $fileInfo->originName;
        if (isset($config['isRandNamed']) && $config['isRandNamed']) {
            $new_file_name = date('YmdHis') . '_' . rand(100, 999);
        }
        $path .= $new_file_name;
        $fileInfo->name = $new_file_name;
        $fileInfo->path = $path;
        if (move_uploaded_file($fileInfo->tmpName, $path)) {
            array_push($this->result, $fileInfo);
            return TRUE;
        }
        if (copy($fileInfo->tmpName, $path)) {
            if (unlink($fileInfo->tmpName)) {
                unset($fileInfo->tmpName);
                array_push($this->result, $fileInfo);
                return TRUE;
            }
            $errors[$fileInfo->originName] = spritf('文件%s移动失败', $fileInfo->originName);
        }
        $errors[$fileInfo->originName] = spritf('文件%s拷贝失败', $fileInfo->originName);
        return FALSE;
    }
}