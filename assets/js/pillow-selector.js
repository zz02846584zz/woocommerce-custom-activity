/**
 * 枕套選擇器
 *
 * 處理枕套選擇的前端邏輯
 */
(function($) {
	'use strict';

	class PillowcaseSelector {
			constructor() {
					this.$selector = $('.nyb-pillowcase-selector');

					if (this.$selector.length === 0) {
							return;
					}

					this.$submitButton = this.$selector.find('.nyb-pillowcase-selector__submit');
					this.$message = this.$selector.find('.nyb-pillowcase-selector__message');
					this.$options = this.$selector.find('input[name="pillowcase_variation"]');

					this.init();
			}

			init() {
					this.$submitButton.on('click', (e) => this.handleSubmit(e));
					this.$options.on('change', () => this.handleOptionChange());
			}

			handleOptionChange() {
					// 啟用提交按鈕
					this.$submitButton.prop('disabled', false);
					this.hideMessage();
			}

			handleSubmit(e) {
					e.preventDefault();

					const variationId = this.$options.filter(':checked').val();

					if (!variationId) {
							this.showMessage('請選擇枕套款式', 'error');
							return;
					}

					this.submitSelection(variationId);
			}

			submitSelection(variationId) {
					const nonce = this.$submitButton.data('nonce');

					// 禁用按鈕
					this.$submitButton.prop('disabled', true).text('處理中...');

					$.ajax({
							url: nybAjax.ajaxUrl,
							type: 'POST',
							data: {
									action: nybAjax.selectPillowAction,
									nonce: nonce,
									variation_id: variationId
							},
							success: (response) => this.handleSuccess(response),
							error: (xhr) => this.handleError(xhr)
					});
			}

			handleSuccess(response) {
					if (response.success) {
							this.showMessage('✓ 枕套款式已選擇', 'success');

							// 2秒後刷新頁面
							setTimeout(() => {
									location.reload();
							}, 2000);
					} else {
							this.showMessage(response.data.message || '選擇失敗', 'error');
							this.$submitButton.prop('disabled', false).text('確認選擇');
					}
			}

			handleError(xhr) {
					console.error('Ajax error:', xhr);
					this.showMessage('發生錯誤，請重試', 'error');
					this.$submitButton.prop('disabled', false).text('確認選擇');
			}

			showMessage(text, type) {
					this.$message
							.removeClass('nyb-pillowcase-selector__message--success nyb-pillowcase-selector__message--error')
							.addClass(`nyb-pillowcase-selector__message--${type}`)
							.text(text)
							.show();
			}

			hideMessage() {
					this.$message.hide();
			}
	}

	// 初始化
	$(document).ready(function() {
			new PillowcaseSelector();
	});

})(jQuery);