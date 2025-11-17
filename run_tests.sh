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

# 运行单元测试
run_unit_tests() {
    print_info "运行单元测试..."
    
    if [ "$1" = "--coverage" ]; then
        ./vendor/bin/phpunit test/UnitTest.php --coverage-html build/coverage --coverage-text
    else
        ./vendor/bin/phpunit test/UnitTest.php --verbose
    fi
    
    if [ $? -eq 0 ]; then
        print_success "单元测试通过"
    else
        print_error "单元测试失败"
        exit 1
    fi
}

# 运行压力测试
run_pressure_tests() {
    print_info "运行压力测试..."
    
    php test/PressureTest.php
    
    if [ $? -eq 0 ]; then
        print_success "压力测试完成"
    else
        print_warning "压力测试发现问题"
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
        ./vendor/bin/phpcs src/ test/ --standard=PSR12
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
        ./vendor/bin/phpstan analyse src/ --level=5
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

### 单元测试
EOF
    
    if [ -f "build/coverage.txt" ]; then
        echo "\`\`\`" >> "$REPORT_FILE"
        cat build/coverage.txt >> "$REPORT_FILE"
        echo "\`\`\`" >> "$REPORT_FILE"
    fi
    
    echo "### 压力测试" >> "$REPORT_FILE"
    echo "压力测试已执行，详细结果请查看控制台输出。" >> "$REPORT_FILE"
    
    echo "测试报告已生成: $REPORT_FILE"
    print_success "测试报告已生成: $REPORT_FILE"
}

# 清理测试文件
cleanup() {
    print_info "清理测试文件..."
    rm -rf build/
    print_success "清理完成"
}

# 显示帮助信息
show_help() {
    echo "PinyinConverter 测试运行脚本"
    echo ""
    echo "用法: $0 [选项]"
    echo ""
    echo "选项:"
    echo "  unit              运行单元测试"
    echo "  unit --coverage   运行单元测试并生成覆盖率报告"
    echo "  pressure          运行压力测试"
    echo "  memory            运行内存泄漏检测"
    echo "  style             运行代码风格检查"
    echo "  static            运行静态分析"
    echo "  all               运行所有测试"
    echo "  report            生成测试报告"
    echo "  cleanup           清理测试文件"
    echo "  help              显示此帮助信息"
    echo ""
    echo "示例:"
    echo "  $0 all                    # 运行所有测试"
    echo "  $0 unit --coverage        # 运行单元测试并生成覆盖率"
    echo "  $0 pressure               # 运行压力测试"
}

# 主函数
main() {
    case "${1:-all}" in
        "unit")
            check_dependencies
            create_directories
            run_unit_tests "$2"
            ;;
        "pressure")
            check_dependencies
            run_pressure_tests
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
            run_unit_tests
            run_pressure_tests
            run_memory_leak_test
            run_code_style_check
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

# 执行主函数
main "$@"