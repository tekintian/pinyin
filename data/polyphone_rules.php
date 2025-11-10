<?php
/** 多音字规则模板（紧凑友好格式，单规则一行，方便修改）
 * type支持三种类型：
 * 1. word: 匹配完整词语  2. pre: 匹配前置汉字  3. post: 匹配后置汉字
 * pinyin 为带声调的准确读音
 */
return [
    // 行：xíng（动作/行为） vs háng（行业/行列）
    '行' => [
        ['type' => 'post', 'char' => '为', 'pinyin' => 'xíng'], // 行为
        ['type' => 'post', 'char' => '动', 'pinyin' => 'xíng'], // 行动
        ['type' => 'post', 'char' => '走', 'pinyin' => 'xíng'], // 行走
        ['type' => 'post', 'char' => '者', 'pinyin' => 'xíng'],  // 行者
        ['type' => 'pre', 'char' => '银', 'pinyin' => 'háng'],  // 银行
        ['type' => 'post', 'char' => '业', 'pinyin' => 'háng'],  // 行业
        ['type' => 'post', 'char' => '列', 'pinyin' => 'háng'],  // 行列
        ['type' => 'word', 'word' => '行话', 'pinyin' => 'háng'], // 行话（行业术语）
    ],

    // 长：cháng（长度） vs zhǎng（生长/领导）
    '长' => [
        ['type' => 'post', 'char' => '度', 'pinyin' => 'cháng'], // 长度
        ['type' => 'post', 'char' => '短', 'pinyin' => 'cháng'], // 长短
        ['type' => 'post', 'char' => '城', 'pinyin' => 'cháng'], // 长城
        ['type' => 'post', 'char' => '大', 'pinyin' => 'zhǎng'], // 长大
        ['type' => 'post', 'char' => '进', 'pinyin' => 'zhǎng'], // 长进
        ['type' => 'pre', 'char' => '校', 'pinyin' => 'zhǎng'],  // 校长
        ['type' => 'word', 'word' => '长期', 'pinyin' => 'cháng'], // 长期
    ],

    // 乐：lè（快乐） vs yuè（音乐）
    '乐' => [
        ['type' => 'post', 'char' => '趣', 'pinyin' => 'lè'],   // 乐趣
        ['type' => 'post', 'char' => '观', 'pinyin' => 'lè'],   // 乐观
        ['type' => 'pre', 'char' => '音', 'pinyin' => 'yuè'],   // 音乐
        ['type' => 'post', 'char' => '器', 'pinyin' => 'yuè'],  // 乐器
        ['type' => 'word', 'word' => '乐园', 'pinyin' => 'lè'], // 乐园
        ['type' => 'word', 'word' => '乐谱', 'pinyin' => 'yuè'], // 乐谱
    ],

    // 发：fā（发生） vs fà（毛发）
    '发' => [
        ['type' => 'post', 'char' => '生', 'pinyin' => 'fā'],   // 发生
        ['type' => 'post', 'char' => '展', 'pinyin' => 'fā'],   // 发展
        ['type' => 'post', 'char' => '现', 'pinyin' => 'fā'],   // 发现
        ['type' => 'pre', 'char' => '头', 'pinyin' => 'fà'],    // 头发
        ['type' => 'post', 'char' => '型', 'pinyin' => 'fà'],   // 发型
        ['type' => 'word', 'word' => '发布', 'pinyin' => 'fā'], // 发布（技术：发布版本）
    ],

    // 重：zhòng（重量） vs chóng（重复）
    '重' => [
        ['type' => 'post', 'char' => '量', 'pinyin' => 'zhòng'], // 重量
        ['type' => 'post', 'char' => '要', 'pinyin' => 'zhòng'], // 重要
        ['type' => 'post', 'char' => '复', 'pinyin' => 'chóng'], // 重复
        ['type' => 'post', 'char' => '新', 'pinyin' => 'chóng'], // 重新
        ['type' => 'word', 'word' => '重构', 'pinyin' => 'chóng'], // 重构（技术：代码重构）
        ['type' => 'word', 'word' => '重点', 'pinyin' => 'zhòng'], // 重点
    ],

    // 参：cān（参与） vs shēn（人参） vs cēn（参差）
    '参' => [
        ['type' => 'post', 'char' => '与', 'pinyin' => 'cān'],   // 参与
        ['type' => 'post', 'char' => '数', 'pinyin' => 'cān'],   // 参数（技术：API参数）
        ['type' => 'pre', 'char' => '人', 'pinyin' => 'shēn'],   // 人参
        ['type' => 'post', 'char' => '差', 'pinyin' => 'cēn'],   // 参差
        ['type' => 'word', 'word' => '参考', 'pinyin' => 'cān'], // 参考
    ],

    // 量：liàng（数量） vs liáng（测量）
    '量' => [
        ['type' => 'post', 'char' => '力', 'pinyin' => 'liàng'], // 力量
        ['type' => 'post', 'char' => '化', 'pinyin' => 'liàng'], // 量化（技术：量化指标）
        ['type' => 'pre', 'char' => '测', 'pinyin' => 'liáng'],  // 测量
        ['type' => 'post', 'char' => '杯', 'pinyin' => 'liáng'], // 量杯
        ['type' => 'word', 'word' => '流量', 'pinyin' => 'liàng'], // 流量（网络流量）
    ],

    // 度：dù（温度） vs duó（揣度）
    '度' => [
        ['type' => 'post', 'char' => '数', 'pinyin' => 'dù'],    // 度数
        ['type' => 'post', 'char' => '量', 'pinyin' => 'dù'],    // 度量
        ['type' => 'pre', 'char' => '揣', 'pinyin' => 'duó'],    // 揣度
        ['type' => 'word', 'word' => '进度', 'pinyin' => 'dù'],  // 进度（项目进度）
        ['type' => 'word', 'word' => '度娘', 'pinyin' => 'dù'],  // 度娘（网络用语）
    ],

    // 数：shù（数字） vs shǔ（数数） vs shuò（数见不鲜）
    '数' => [
        ['type' => 'post', 'char' => '字', 'pinyin' => 'shù'],   // 数字
        ['type' => 'post', 'char' => '据', 'pinyin' => 'shù'],   // 数据（数据库）
        ['type' => 'post', 'char' => '量', 'pinyin' => 'shù'],   // 数量
        ['type' => 'post', 'char' => '数', 'pinyin' => 'shǔ'],   // 数数（动作）
        ['type' => 'word', 'word' => '数模', 'pinyin' => 'shù'], // 数模（数学模型）
    ],

    // 中：zhōng（中间） vs zhòng（中奖）
    '中' => [
        ['type' => 'post', 'char' => '间', 'pinyin' => 'zhōng'], // 中间
        ['type' => 'post', 'char' => '心', 'pinyin' => 'zhōng'], // 中心（数据中台）
        ['type' => 'post', 'char' => '奖', 'pinyin' => 'zhòng'], // 中奖
        ['type' => 'post', 'char' => '靶', 'pinyin' => 'zhòng'], // 中靶
        ['type' => 'word', 'word' => '中台', 'pinyin' => 'zhōng'], // 中台（数据中台）
    ],

    // 盛：shèng（盛开） vs chéng（盛饭）
    '盛' => [
        ['type' => 'post', 'char' => '开', 'pinyin' => 'shèng'], // 盛开
        ['type' => 'post', 'char' => '行', 'pinyin' => 'shèng'], // 盛行
        ['type' => 'post', 'char' => '饭', 'pinyin' => 'chéng'], // 盛饭
        ['type' => 'word', 'word' => '盛世', 'pinyin' => 'shèng'], // 盛世
    ],

    // 奔：bēn（奔跑） vs bèn（投奔）
    '奔' => [
        ['type' => 'post', 'char' => '跑', 'pinyin' => 'bēn'],   // 奔跑
        ['type' => 'post', 'char' => '驰', 'pinyin' => 'bēn'],   // 奔驰
        ['type' => 'post', 'char' => '赴', 'pinyin' => 'bèn'],   // 奔赴
        ['type' => 'pre', 'char' => '投', 'pinyin' => 'bèn'],    // 投奔
        ['type' => 'word', 'word' => '奔腾', 'pinyin' => 'bēn'], // 奔腾（芯片品牌）
    ],

    // 调：tiáo（调节） vs diào（调动）
    '调' => [
        ['type' => 'post', 'char' => '节', 'pinyin' => 'tiáo'],  // 调节
        ['type' => 'post', 'char' => '整', 'pinyin' => 'tiáo'],  // 调整
        ['type' => 'post', 'char' => '动', 'pinyin' => 'diào'],  // 调动
        ['type' => 'post', 'char' => '试', 'pinyin' => 'diào'],  // 调试（技术：代码调试）
        ['type' => 'word', 'word' => '调度', 'pinyin' => 'diào'], // 调度（任务调度）
    ],
    '单' => [
        ['type' => 'post', 'char' => '于', 'pinyin' => 'chán'],  // 单于
    ],
    '尉'=>[
        ['type' => 'post', 'char' => '迟', 'pinyin' => 'yù'],   // 尉迟
    ],
    '说'=>[
        ['type' => 'post', 'char' => '客', 'pinyin' => 'shuì'],   // 说客
        ['type' => 'pre', 'char' => '游', 'pinyin' => 'shuì'],   // 游说
    ],
];
