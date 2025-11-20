#!/bin/bash

# PinyinConverter 测试运行脚本
# 提供便捷的测试执行和报告生成功能

set -e

# 颜色定义
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
BLUE='\033[0;34m'
NC='\033[0m' # No Color

# 打印带颜色的消息
print_info() {
    echo -e "${BLUE}[INFO]${NC} $1"
}

print_success() {
    echo -e "${GREEN}[SUCCESS]${NC} $1"
}

print_warning() {
    echo -e "${YELLOW}[WARNING]${NC} $1"
}

print_error() {
    echo -e "${RED}[ERROR]${NC} $1"
}

# 检查依赖
check_dependencies() {
    print_info "检查依赖..."
    
    if ! command -v php &> /dev/null; then
        print_error "PHP 未安装或不在 PATH 中"
        exit 1
    fi
    
    if [ ! -f "vendor/autoload.php" ]; then
        print_info "安装 Composer 依赖..."
        composer install
    fi
    
    if [ ! -f "vendor/bin/phpunit" ]; then
        print_error "PHPUnit 未安装，请运行 composer install"
        exit 1
    fi
    
    print_success "依赖检查完成"
}

# 创建必要的目录
create_directories() {
    print_info "创建测试目录..."
    mkdir -p build/coverage
    mkdir -p build/logs
    mkdir -p build/reports
}

# 运行基础转换测试
run_basic_tests() {
    print_info "运行基础转换测试..."
    ./vendor/bin/phpunit --configuration tests/phpunit.xml tests/Unit/BasicConversionTest.php --verbose
    
    if [ $? -eq 0 ]; then
        print_success "基础转换测试通过"
    else
        print_error "基础转换测试失败"
        exit 1
    fi
}

# 运行多音字测试
run_polyphone_tests() {
    print_info "运行多音字测试..."
    ./vendor/bin/phpunit --configuration tests/phpunit.xml tests/Unit/PolyphoneTest.php --verbose
    
    if [ $? -eq 0 ]; then
        print_success "多音字测试通过"
    else
        print_error "多音字测试失败"
        exit 1
    fi
}

# 运行特殊字符测试
run_special_character_tests() {
    print_info "运行特殊字符测试..."
    ./vendor/bin/phpunit --configuration tests/phpunit.xml tests/Unit/SpecialCharacterTest.php --verbose
    
    if [ $? -eq 0 ]; then
        print_success "特殊字符测试通过"
    else
        print_error "特殊字符测试失败"
        exit 1
    fi
}

# 运行自定义字典测试
run_custom_dict_tests() {
    print_info "运行自定义字典测试..."
    ./vendor/bin/phpunit --configuration tests/phpunit.xml tests/Unit/CustomDictionaryTest.php --verbose
    
    if [ $? -eq 0 ]; then
        print_success "自定义字典测试通过"
    else
        print_error "自定义字典测试失败"
        exit 1
    fi
}

# 运行边界条件测试
run_edge_case_tests() {
    print_info "运行边界条件测试..."
    ./vendor/bin/phpunit --configuration tests/phpunit.xml tests/Unit/EdgeCaseTest.php --verbose
    
    if [ $? -eq 0 ]; then
        print_success "边界条件测试通过"
    else
        print_error "边界条件测试失败"
        exit 1
    fi
}

# 运行单元测试套件
run_unit_tests() {
    print_info "运行单元测试套件..."
    
    if [ "$1" = "--coverage" ]; then
        ./vendor/bin/phpunit --configuration tests/phpunit.xml --testsuite Unit --coverage-html build/coverage --coverage-text
    else
        ./vendor/bin/phpunit --configuration tests/phpunit.xml --testsuite Unit --verbose
    fi
    
    if [ $? -eq 0 ]; then
        print_success "单元测试套件通过"
    else
        print_error "单元测试套件失败"
        exit 1
    fi
}

# 运行集成测试
run_integration_tests() {
    print_info "运行集成测试..."
    ./vendor/bin/phpunit --configuration tests/phpunit.xml --testsuite Integration --verbose
    
    if [ $? -eq 0 ]; then
        print_success "集成测试通过"
    else
        print_error "集成测试失败"
        exit 1
    fi
}

# 运行性能测试
run_performance_tests() {
    print_info "运行性能测试..."
    ./vendor/bin/phpunit --configuration tests/phpunit.xml --testsuite Performance --verbose
    
    if [ $? -eq 0 ]; then
        print_success "性能测试通过"
    else
        print_warning "性能测试发现问题"
    fi
}

# 运行快速测试（排除性能测试）
run_fast_tests() {
    print_info "运行快速测试..."
    ./vendor/bin/phpunit --configuration tests/phpunit.xml --testsuite Fast --verbose
    
    if [ $? -eq 0 ]; then
        print_success "快速测试通过"
    else
        print_error "快速测试失败"
        exit 1
    fi
}

# 运行完整测试套件
run_complete_tests() {
    print_info "运行完整测试套件..."
    
    if [ "$1" = "--coverage" ]; then
        ./vendor/bin/phpunit --configuration tests/phpunit.xml --testsuite Complete --coverage-html build/coverage --coverage-text
    else
        ./vendor/bin/phpunit --configuration tests/phpunit.xml --testsuite Complete --verbose
    fi
    
    if [ $? -eq 0 ]; then
        print_success "完整测试套件通过"
    else
        print_error "完整测试套件失败"
        exit 1
    fi
}

# 运行内存泄漏检测
run_memory_leak_test() {
    print_info "运行内存泄漏检测..."
    
    php -r '
    require_once "vendor/autoload.php";
    use tekintian\pinyin\PinyinConverter;
    
    $converter = new PinyinConverter(["custom_dict_persistence" => ["enable_delayed_write" => false]]);
    $memory_readings = [];
    
    echo "开始内存泄漏检测（10000次迭代）...\n";
    
    for ($i = 0; $i < 10000; $i++) {
        $converter->convert("内存泄漏检测测试文本");
        if ($i % 500 === 0) {
            $memory_readings[] = memory_get_usage(true);
            if (count($memory_readings) > 1) {
                $increase = $memory_readings[count($memory_readings)-1] - $memory_readings[0];
                if ($increase > 10 * 1024 * 1024) {
                    echo "警告：检测到显著内存增长: " . round($increase/1024/1024,2) . " MB\n";
                    exit(1);
                }
            }
        }
    }
    echo "内存泄漏检测通过\n";
    '
    
    if [ $? -eq 0 ]; then
        print_success "内存泄漏检测通过"
    else
        print_error "内存泄漏检测失败"
        exit 1
    fi
}

# 运行代码风格检查
run_code_style_check() {
    print_info "运行代码风格检查..."
    
    if command -v ./vendor/bin/phpcs &> /dev/null; then
        ./vendor/bin/phpcs src/ --standard=PSR12 --warning-severity=0
        if [ $? -eq 0 ]; then
            print_success "代码风格检查通过"
        else
            print_warning "代码风格检查发现问题"
        fi
    else
        print_warning "PHP_CodeSniffer 未安装，跳过代码风格检查"
    fi
}

# 运行静态分析
run_static_analysis() {
    print_info "运行静态分析..."
    
    if command -v ./vendor/bin/phpstan &> /dev/null; then
        ./vendor/bin/phpstan analyse src/ --level=5 --memory-limit=512M
        if [ $? -eq 0 ]; then
            print_success "静态分析通过"
        else
            print_warning "静态分析发现问题"
        fi
    else
        print_warning "PHPStan 未安装，跳过静态分析"
    fi
}

# 生成测试报告
generate_report() {
    print_info "生成测试报告..."
    
    REPORT_FILE="build/reports/test_report_$(date +%Y%m%d_%H%M%S).md"
    
    cat > "$REPORT_FILE" << EOF
# PinyinConverter 测试报告

## 测试信息
- 测试时间: $(date)
- PHP版本: $(php -v | head -n 1)
- 测试环境: $(uname -a)

## 测试结果

### 单元测试套件
EOF
    
    if [ -f "build/coverage.txt" ]; then
        echo "\`\`\`" >> "$REPORT_FILE"
        cat build/coverage.txt >> "$REPORT_FILE"
        echo "\`\`\`" >> "$REPORT_FILE"
    fi
    
    echo "### 集成测试" >> "$REPORT_FILE"
    echo "集成测试已执行，详细结果请查看控制台输出。" >> "$REPORT_FILE"
    
    echo "### 性能测试" >> "$REPORT_FILE"
    echo "性能测试已执行，详细结果请查看控制台输出。" >> "$REPORT_FILE"
    
    echo "测试报告已生成: $REPORT_FILE"
    print_success "测试报告已生成: $REPORT_FILE"
}

# 清理测试文件
cleanup() {
    print_info "清理测试文件..."
    if [ -e ".phpunit.result.cache" ]; then
        rm -rf .phpunit.result.cache
    fi

    rm -rf build/
    print_success "清理完成"
}

# 显示帮助信息
show_help() {
    echo "PinyinConverter 测试运行脚本"
    echo ""
    echo "用法: $0 [选项]"
    echo ""
    echo "基础测试选项:"
    echo "  basic             运行基础转换测试"
    echo "  polyphone         运行多音字测试"
    echo "  special           运行特殊字符测试"
    echo "  custom            运行自定义字典测试"
    echo "  edge              运行边界条件测试"
    echo ""
    echo "测试套件选项:"
    echo "  unit              运行单元测试套件"
    echo "  integration       运行集成测试"
    echo "  performance       运行性能测试"
    echo "  fast              运行快速测试（排除性能测试）"
    echo "  complete          运行完整测试套件"
    echo ""
    echo "覆盖率选项:"
    echo "  unit --coverage   运行单元测试并生成覆盖率报告"
    echo "  complete --coverage 运行完整测试并生成覆盖率报告"
    echo ""
    echo "其他选项:"
    echo "  memory            运行内存泄漏检测"
    echo "  style             运行代码风格检查"
    echo "  static            运行静态分析"
    echo "  all               运行所有测试"
    echo "  report            生成测试报告"
    echo "  cleanup           清理测试文件"
    echo "  migrate           迁移旧测试文件"
    echo "  help              显示此帮助信息"
    echo ""
    echo "示例:"
    echo "  $0 all                    # 运行所有测试"
    echo "  $0 unit --coverage        # 运行单元测试并生成覆盖率"
    echo "  $0 fast                   # 运行快速测试"
    echo "  $0 basic                  # 运行基础转换测试"
}

# 主函数
main() {
    case "${1:-all}" in
        "basic")
            check_dependencies
            create_directories
            run_basic_tests
            ;;
        "polyphone")
            check_dependencies
            create_directories
            run_polyphone_tests
            ;;
        "special")
            check_dependencies
            create_directories
            run_special_character_tests
            ;;
        "custom")
            check_dependencies
            create_directories
            run_custom_dict_tests
            ;;
        "edge")
            check_dependencies
            create_directories
            run_edge_case_tests
            ;;
        "unit")
            check_dependencies
            create_directories
            run_unit_tests "$2"
            ;;
        "integration")
            check_dependencies
            create_directories
            run_integration_tests
            ;;
        "performance")
            check_dependencies
            create_directories
            run_performance_tests
            ;;
        "fast")
            check_dependencies
            create_directories
            run_fast_tests
            ;;
        "complete")
            check_dependencies
            create_directories
            run_complete_tests "$2"
            ;;
        "memory")
            check_dependencies
            run_memory_leak_test
            ;;
        "style")
            check_dependencies
            run_code_style_check
            ;;
        "static")
            check_dependencies
            run_static_analysis
            ;;
        "all")
            check_dependencies
            create_directories
            run_basic_tests
            run_polyphone_tests
            run_special_character_tests
            run_custom_dict_tests
            run_edge_case_tests
            run_integration_tests
            run_performance_tests
            run_memory_leak_test
            # run_code_style_check
            run_static_analysis
            generate_report
            ;;
        "report")
            generate_report
            ;;
        "cleanup")
            cleanup
            ;;
        "help"|"-h"|"--help")
            show_help
            ;;
        *)
            print_error "未知选项: $1"
            show_help
            exit 1
            ;;
    esac
}

cleanup

# 执行主函数
main "$@"