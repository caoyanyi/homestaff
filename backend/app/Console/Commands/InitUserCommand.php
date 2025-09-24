<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\User;

class InitUserCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'user:init
                            {--name=admin : 用户名}
                            {--email=admin@example.com : 用户邮箱}
                            {--password=password : 用户密码}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = '初始化系统用户，创建管理员账户';

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
     * @return int
     */
    public function handle()
    {
        $name = $this->option('name');
        $email = $this->option('email');
        $password = $this->option('password');

        // 检查用户是否已存在
        if (User::where('email', $email)->exists()) {
            $this->error('用户已存在: ' . $email);
            return 1;
        }

        // 创建新用户
        $user = User::create([
            'name' => $name,
            'email' => $email,
            'password' => bcrypt($password),
            'email_verified_at' => now(),
        ]);

        $this->info('用户创建成功!');
        $this->info('用户名: ' . $name);
        $this->info('邮箱: ' . $email);
        $this->info('密码: ' . $password);
        $this->info('请在首次登录后修改密码');

        return 0;
    }
}
