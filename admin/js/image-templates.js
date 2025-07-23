/**
 * UniVoucher Image Templates Admin JavaScript
 */

jQuery(document).ready(function($) {
    'use strict';

    // Initialize image templates functionality
    var UniVoucherImageTemplates = {
        
        // Current scale factor for position calculation
        scaleFactor: 1,
        
        // Original template dimensions (will be set dynamically)
        originalWidth: 700,
        originalHeight: 800,

        /**
         * Initialize the image templates interface
         */
        init: function() {
            // Check if we're on the image templates tab
            if (!$('#univoucher-image-editor-container').length) {
                return;
            }
            
            this.initColorPicker();
            this.initDraggable();
            this.initEventHandlers();
            this.initTemplateTracking();
            this.loadTemplatePreview();
            this.initLogoImageHandlers();
            this.initCustomResources();
            this.initCustomFontPicker();
            
            // Initial setup with a small delay to ensure DOM is ready
            var self = this;
            setTimeout(function() {
                self.updateScaleFactor();
                self.updateAllElementPositions();
                self.updateAllPreviews();
                self.setupColorPickerBehavior();
                
                // Auto-select amount with token element if available
                if ($('#univoucher_wc_image_show_amount_with_symbol').is(':checked') && $('#univoucher-draggable-amount-with-symbol').is(':visible')) {
                    // Ensure element position is fully updated before selecting
                    self.updateAmountWithSymbolPosition();
                    setTimeout(function() {
                        self.selectElement('#univoucher-draggable-amount-with-symbol', 'amount_with_symbol');
                    }, 50);
                } else {
                    // Highlight the help text if no element is auto-selected
                    $('#no-selection-help').addClass('highlighted');
                }
            }, 100);
        },

        /**
         * Initialize custom font picker
         */
        initCustomFontPicker: function() {
            var self = this;
            
            // Handle font picker click
            $('.custom-font-picker').on('click', function(e) {
                if ($(this).hasClass('disabled')) {
                    return;
                }
                
                var dropdown = $(this).find('.font-picker-dropdown');
                var isVisible = dropdown.is(':visible');
                
                // Close all other dropdowns
                $('.font-picker-dropdown').hide();
                $('.custom-font-picker').removeClass('active');
                
                if (!isVisible) {
                    dropdown.show();
                    $(this).addClass('active');
                }
            });
            
            // Handle font option selection
            $('.font-option').on('click', function(e) {
                e.stopPropagation();
                
                var value = $(this).data('value');
                var text = $(this).text();
                var picker = $(this).closest('.custom-font-picker');
                
                // Update selected text
                picker.find('.font-picker-text').text(text);
                
                // Update hidden input value
                picker.attr('data-value', value);
                
                // Close dropdown
                picker.find('.font-picker-dropdown').hide();
                picker.removeClass('active');
                
                // Trigger change event
                self.applyElementSettings();
            });
            
            // Close dropdown when clicking outside
            $(document).on('click', function(e) {
                if (!$(e.target).closest('.custom-font-picker').length) {
                    $('.font-picker-dropdown').hide();
                    $('.custom-font-picker').removeClass('active');
                }
            });
        },

        /**
         * Initialize color picker with proper configuration
         */
        initColorPicker: function() {
            var self = this;
            
            // Initialize element color picker with custom settings
            $('#element-color').wpColorPicker({
                change: function(event, ui) {
                    // Only trigger if element is not disabled
                    if (!$(this).prop('disabled')) {
                        self.applyElementSettings();
                    }
                },
                clear: function() {
                    // Only trigger if element is not disabled
                    if (!$(this).prop('disabled')) {
                        self.applyElementSettings();
                    }
                },
                // Position the picker above the controllers
                hide: true,
                palettes: true,
                // Handle opening of color picker
                open: function() {
                    self.positionColorPicker();
                }
            });

            // Initialize hidden color pickers for individual elements
            $('#univoucher_wc_image_amount_with_symbol_color').wpColorPicker({
                change: function(event, ui) {
                    self.updateAmountWithSymbolPreview();
                },
                clear: function() {
                    $(this).val('#000000');
                    self.updateAmountWithSymbolPreview();
                }
            });

            $('#univoucher_wc_image_amount_color').wpColorPicker({
                change: function(event, ui) {
                    self.updateAmountPreview();
                },
                clear: function() {
                    $(this).val('#000000');
                    self.updateAmountPreview();
                }
            });

            $('#univoucher_wc_image_token_symbol_color').wpColorPicker({
                change: function(event, ui) {
                    self.updateTokenSymbolPreview();
                },
                clear: function() {
                    $(this).val('#000000');
                    self.updateTokenSymbolPreview();
                }
            });

            $('#univoucher_wc_image_network_name_color').wpColorPicker({
                change: function(event, ui) {
                    self.updateNetworkNamePreview();
                },
                clear: function() {
                    $(this).val('#000000');
                    self.updateNetworkNamePreview();
                }
            });
        },

        /**
         * Position color picker properly when opened
         */
        positionColorPicker: function() {
            var $button = $('#element-color').siblings('.wp-color-result');
            var $picker = $('#element-color').siblings('.wp-picker-holder');
            
            if ($button.length && $picker.length) {
                var buttonOffset = $button.offset();
                var buttonHeight = $button.outerHeight();
                var pickerHeight = $picker.outerHeight();
                var windowHeight = $(window).height();
                var scrollTop = $(window).scrollTop();
                
                // Calculate position
                var top = buttonOffset.top - pickerHeight - 10;
                var left = buttonOffset.left;
                
                // If picker would go above viewport, show it below the button
                if (top < scrollTop) {
                    top = buttonOffset.top + buttonHeight + 10;
                }
                
                // Ensure picker doesn't go off screen horizontally
                var pickerWidth = $picker.outerWidth();
                var windowWidth = $(window).width();
                if (left + pickerWidth > windowWidth) {
                    left = windowWidth - pickerWidth - 20;
                }
                if (left < 10) {
                    left = 10;
                }
                
                // Apply position
                $picker.css({
                    position: 'fixed',
                    top: top + 'px',
                    left: left + 'px',
                    zIndex: 999999
                });
            }
        },

        /**
         * Setup color picker behavior for enable/disable
         */
        setupColorPickerBehavior: function() {
            var self = this;
            
            // Store reference to the color picker wrapper
            this.$colorPickerWrapper = $('#element-color').closest('.wp-picker-container');
            
            // Initially disable the color picker
            this.disableColorPicker();
            
            // Handle color picker button clicks for positioning
            $(document).on('click', '#element-color + .wp-color-result', function(e) {
                if (!$(this).siblings('#element-color').prop('disabled')) {
                    // Small delay to ensure picker is rendered
                    setTimeout(function() {
                        self.positionColorPicker();
                    }, 10);
                }
            });
            
            // Also handle when picker appears via other means
            var observer = new MutationObserver(function(mutations) {
                mutations.forEach(function(mutation) {
                    if (mutation.type === 'childList') {
                        var $holder = $(mutation.target).find('.wp-picker-holder');
                        if ($holder.length && $holder.is(':visible')) {
                            self.positionColorPicker();
                        }
                    }
                });
            });
            
            if (this.$colorPickerWrapper && this.$colorPickerWrapper.length) {
                observer.observe(this.$colorPickerWrapper[0], {
                    childList: true,
                    subtree: true
                });
            }
        },

        /**
         * Enable color picker
         */
        enableColorPicker: function() {
            var $input = $('#element-color');
            var $wrapper = this.$colorPickerWrapper;
            
            if ($wrapper && $wrapper.length) {
                $input.prop('disabled', false);
                $wrapper.removeClass('wp-picker-disabled');
                $wrapper.find('.wp-color-result').prop('disabled', false);
                $wrapper.find('.wp-picker-clear').prop('disabled', false);
            }
        },

        /**
         * Disable color picker
         */
        disableColorPicker: function() {
            var $input = $('#element-color');
            var $wrapper = this.$colorPickerWrapper;
            
            if ($wrapper && $wrapper.length) {
                $input.prop('disabled', true);
                $wrapper.addClass('wp-picker-disabled');
                $wrapper.find('.wp-color-result').prop('disabled', true);
                $wrapper.find('.wp-picker-clear').prop('disabled', true);
                
                // Hide any open color picker
                $wrapper.find('.wp-picker-holder').hide();
            }
        },

        /**
         * Initialize draggable functionality for all text elements and logos
         */
        initDraggable: function() {
            var self = this;
            
            // Amount with symbol text draggable
            $('#univoucher-draggable-amount-with-symbol').draggable({
                containment: '#univoucher-template-preview',
                scroll: false,
                start: function(event, ui) {
                    self.isDragging = true;
                },
                drag: function(event, ui) {
                    UniVoucherImageTemplates.updateTextPositionInputs(ui.position, this);
                    self.updateResizeControlsPosition($(this));
                },
                stop: function(event, ui) {
                    UniVoucherImageTemplates.updateTextPositionInputs(ui.position, this);
                    self.isDragging = false;
                }
            }).on('mouseenter', function() {
                if (!self.isDragging) {
                    self.activateElement(this, 'amount_with_symbol');
                }
            }).on('mouseleave', function() {
                if (!self.isDragging) {
                    self.deactivateElement(this);
                }
            }).on('click', function(e) {
                e.stopPropagation();
                self.selectElement(this, 'amount_with_symbol');
            });
            
            // Amount text draggable
            $('#univoucher-draggable-amount').draggable({
                containment: '#univoucher-template-preview',
                scroll: false,
                start: function(event, ui) {
                    self.isDragging = true;
                },
                drag: function(event, ui) {
                    UniVoucherImageTemplates.updateTextPositionInputs(ui.position, this);
                    self.updateResizeControlsPosition($(this));
                },
                stop: function(event, ui) {
                    UniVoucherImageTemplates.updateTextPositionInputs(ui.position, this);
                    self.isDragging = false;
                }
            }).on('mouseenter', function() {
                if (!self.isDragging) {
                    self.activateElement(this, 'amount');
                }
            }).on('mouseleave', function() {
                if (!self.isDragging) {
                    self.deactivateElement(this);
                }
            }).on('click', function(e) {
                e.stopPropagation();
                self.selectElement(this, 'amount');
            });

            // Token symbol draggable  
            $('#univoucher-draggable-token-symbol').draggable({
                containment: '#univoucher-template-preview',
                scroll: false,
                start: function(event, ui) {
                    self.isDragging = true;
                },
                drag: function(event, ui) {
                    UniVoucherImageTemplates.updateTextPositionInputs(ui.position, this);
                    self.updateResizeControlsPosition($(this));
                },
                stop: function(event, ui) {
                    UniVoucherImageTemplates.updateTextPositionInputs(ui.position, this);
                    self.isDragging = false;
                }
            }).on('mouseenter', function() {
                if (!self.isDragging) {
                    self.activateElement(this, 'token_symbol');
                }
            }).on('mouseleave', function() {
                if (!self.isDragging) {
                    self.deactivateElement(this);
                }
            }).on('click', function(e) {
                e.stopPropagation();
                self.selectElement(this, 'token_symbol');
            });

            // Network name draggable
            $('#univoucher-draggable-network-name').draggable({
                containment: '#univoucher-template-preview',
                scroll: false,
                start: function(event, ui) {
                    self.isDragging = true;
                },
                drag: function(event, ui) {
                    UniVoucherImageTemplates.updateTextPositionInputs(ui.position, this);
                    self.updateResizeControlsPosition($(this));
                },
                stop: function(event, ui) {
                    UniVoucherImageTemplates.updateTextPositionInputs(ui.position, this);
                    self.isDragging = false;
                }
            }).on('mouseenter', function() {
                if (!self.isDragging) {
                    self.activateElement(this, 'network_name');
                }
            }).on('mouseleave', function() {
                if (!self.isDragging) {
                    self.deactivateElement(this);
                }
            }).on('click', function(e) {
                e.stopPropagation();
                self.selectElement(this, 'network_name');
            });

            // Network logo draggable
            $('#univoucher-draggable-logo').draggable({
                containment: '#univoucher-template-preview',
                scroll: false,
                start: function(event, ui) {
                    self.isDragging = true;
                },
                drag: function(event, ui) {
                    UniVoucherImageTemplates.updateLogoPositionInputs(ui.position);
                    self.updateResizeControlsPosition($(this));
                },
                stop: function(event, ui) {
                    UniVoucherImageTemplates.updateLogoPositionInputs(ui.position);
                    self.isDragging = false;
                }
            }).on('mouseenter', function() {
                if (!self.isDragging) {
                    self.activateElement(this, 'logo');
                }
            }).on('mouseleave', function() {
                if (!self.isDragging) {
                    self.deactivateElement(this);
                }
            }).on('click', function(e) {
                e.stopPropagation();
                self.selectElement(this, 'logo');
            });

            // Token logo draggable
            $('#univoucher-draggable-token').draggable({
                containment: '#univoucher-template-preview',
                scroll: false,
                start: function(event, ui) {
                    self.isDragging = true;
                },
                drag: function(event, ui) {
                    UniVoucherImageTemplates.updateTokenPositionInputs(ui.position);
                    self.updateResizeControlsPosition($(this));
                },
                stop: function(event, ui) {
                    UniVoucherImageTemplates.updateTokenPositionInputs(ui.position);
                    self.isDragging = false;
                }
            }).on('mouseenter', function() {
                if (!self.isDragging) {
                    self.activateElement(this, 'token');
                }
            }).on('mouseleave', function() {
                if (!self.isDragging) {
                    self.deactivateElement(this);
                }
            }).on('click', function(e) {
                e.stopPropagation();
                self.selectElement(this, 'token');
            });

            // Keep controls active when hovering over controls container or alignment indicators
            $(document).on('mouseenter', '.univoucher-resize-controls, .alignment-indicator', function() {
                clearTimeout(self.hideControlsTimeout);
            }).on('mouseleave', '.univoucher-resize-controls, .alignment-indicator', function() {
                if (!self.selectedElement) {
                    self.deactivateElement();
                }
            });

            // Deselect element when clicking outside
            $(document).on('click', function(e) {
                if (!$(e.target).closest('.draggable-element, .univoucher-element-controllers, .univoucher-resize-controls, .alignment-indicator').length) {
                    self.deselectElement();
                }
            });

            // Bind element control changes
            $('#element-size, #element-color, #element-align').on('change input', function() {
                self.applyElementSettings();
            });
        },



        /**
         * Position resize controls relative to element
         */
        positionResizeControls: function($controls, $element) {
            var $container = $('#univoucher-image-editor');
            var elementOffset = $element.position();
            var containerWidth = $container.width();
            
            // Calculate position (above the element, centered)
            var top = elementOffset.top - 35;
            var left = elementOffset.left + ($element.outerWidth() / 2) - 25;
            
            // Ensure controls stay within container bounds
            if (left < 5) left = 5;
            if (left > containerWidth - 55) left = containerWidth - 55;
            if (top < 5) top = elementOffset.top + $element.outerHeight() + 5;
            
            $controls.css({
                position: 'absolute',
                top: top + 'px',
                left: left + 'px',
                zIndex: 1000
            });
        },

        /**
         * Update element position during dragging
         */
        updateResizeControlsPosition: function($element) {
            if (this.currentElement && this.currentElement.is($element)) {
                var $controls = $('.univoucher-resize-controls');
                if ($controls.length) {
                    this.positionResizeControls($controls, $element);
                }
                
                // Update alignment indicators position if this is a text element
                var elementType = this.currentElementType;
                if (elementType === 'amount_with_symbol' || elementType === 'amount' || elementType === 'token_symbol' || elementType === 'network_name') {
                    this.showAlignmentIndicators($element, elementType);
                }
            }
        },

        /**
         * Bind resize events with long press support
         */
        bindResizeEvents: function($controls, type) {
            var self = this;
            var longPressTimer;
            var isLongPressing = false;
            var longPressInterval;
            
            // Minus button events
            $controls.find('.resize-minus')
                .on('mousedown touchstart', function(e) {
                    e.preventDefault();
                    e.stopPropagation();
                    
                    // Immediate resize
                    self.resizeElement(type, -2);
                    
                    // Start long press timer
                    longPressTimer = setTimeout(function() {
                        isLongPressing = true;
                        longPressInterval = setInterval(function() {
                            self.resizeElement(type, -2);
                        }, 20); // Repeat every 20ms
                    }, 500); // Long press after 500ms
                })
                .on('mouseup mouseleave touchend', function(e) {
                    e.preventDefault();
                    e.stopPropagation();
                    
                    clearTimeout(longPressTimer);
                    clearInterval(longPressInterval);
                    isLongPressing = false;
                });
            
            // Plus button events
            $controls.find('.resize-plus')
                .on('mousedown touchstart', function(e) {
                    e.preventDefault();
                    e.stopPropagation();
                    
                    // Immediate resize
                    self.resizeElement(type, 2);
                    
                    // Start long press timer
                    longPressTimer = setTimeout(function() {
                        isLongPressing = true;
                        longPressInterval = setInterval(function() {
                            self.resizeElement(type, 2);
                        }, 20); // Repeat every 20ms
                    }, 500); // Long press after 500ms
                })
                .on('mouseup mouseleave touchend', function(e) {
                    e.preventDefault();
                    e.stopPropagation();
                    
                    clearTimeout(longPressTimer);
                    clearInterval(longPressInterval);
                    isLongPressing = false;
                });
        },



        /**
         * Resize an element by changing its size value (prioritizes hovered element over selected element)
         */
        resizeElement: function(type, change) {
            var currentSize, newSize, $field;
            
            // If a specific type is provided (from hovered element), use that
            if (type) {
                // For all text elements, use their individual size settings
                if (type === 'amount_with_symbol') {
                    $field = $('#univoucher_wc_image_amount_with_symbol_size');
                } else if (type === 'amount') {
                    $field = $('#univoucher_wc_image_amount_size');
                } else if (type === 'token_symbol') {
                    $field = $('#univoucher_wc_image_token_symbol_size');
                } else if (type === 'network_name') {
                    $field = $('#univoucher_wc_image_network_name_size');
                } else if (type === 'logo') {
                    $field = $('#univoucher_wc_image_logo_height');
                } else if (type === 'token') {
                    $field = $('#univoucher_wc_image_token_height');
                } else {
                    return;
                }
                
                currentSize = parseInt($field.val(), 10) || 0;
                
                if (type === 'logo' || type === 'token') {
                    newSize = Math.max(20, currentSize + change);
                    newSize = Math.min(500, newSize);
                } else {
                    newSize = Math.max(8, currentSize + change);
                    newSize = Math.min(500, newSize);
                }
                
                $field.val(newSize).trigger('change');
                
                // Update controller field if this is the selected element
                if (this.selectedElement && this.selectedElementType === type) {
                    $('#element-size').val(newSize);
                }
                return;
            }
            
            // Fallback: If no specific type provided but an element is selected, resize the selected element
            if (this.selectedElement && this.selectedElementType) {
                var selectedType = this.selectedElementType;
                
                if (selectedType === 'amount_with_symbol' || selectedType === 'amount' || selectedType === 'token_symbol' || selectedType === 'network_name') {
                    var currentSize = parseInt($('#element-size').val(), 10) || 0;
                    var newSize = Math.max(8, currentSize + change);
                    newSize = Math.min(200, newSize);
                    
                    $('#element-size').val(newSize).trigger('change');
                    return;
                }
                
                if (selectedType === 'logo' || selectedType === 'token') {
                    var currentSize = parseInt($('#element-size').val(), 10) || 0;
                    var newSize = Math.max(20, currentSize + change);
                    newSize = Math.min(500, newSize);
                    
                    $('#element-size').val(newSize).trigger('change');
                    return;
                }
            }
        },

        /**
         * Initialize logo image handlers
         */
        initLogoImageHandlers: function() {
            var self = this;
            
            // Handle logo image load to ensure proper sizing
            $('#univoucher-draggable-logo img').on('load', function() {
                setTimeout(function() {
                    self.updateLogoPreview();
                }, 10);
            });

            // Handle token image load to ensure proper sizing
            $('#univoucher-draggable-token img').on('load', function() {
                setTimeout(function() {
                    self.updateTokenPreview();
                }, 10);
            });
        },

        /**
         * Initialize template tracking
         */
        initTemplateTracking: function() {
            var $templateSelect = $('#univoucher_wc_image_template');
            // Store the initial value
            $templateSelect.data('previous-value', $templateSelect.val());
            
            // Update previous value on successful change
            $templateSelect.on('change', function() {
                // This will be updated after confirmation in handleTemplateChange
                setTimeout(function() {
                    $templateSelect.data('previous-value', $templateSelect.val());
                }, 100);
            });
        },

        /**
         * Initialize event handlers
         */
        initEventHandlers: function() {
            var self = this;

            // Template selection change
            $('#univoucher_wc_image_template').on('change', function() {
                self.handleTemplateChange();
            });

            // Amount with symbol text style changes
            $('#univoucher_wc_image_amount_with_symbol_font, #univoucher_wc_image_amount_with_symbol_size, #univoucher_wc_image_amount_with_symbol_color, #univoucher_wc_image_amount_with_symbol_align').on('change input', function() {
                self.updateAmountWithSymbolPreview();
            });

            // Amount text style changes
            $('#univoucher_wc_image_amount_font, #univoucher_wc_image_amount_size, #univoucher_wc_image_amount_color, #univoucher_wc_image_amount_align').on('change input', function() {
                self.updateAmountPreview();
            });

            // Token symbol text style changes
            $('#univoucher_wc_image_token_symbol_font, #univoucher_wc_image_token_symbol_size, #univoucher_wc_image_token_symbol_color, #univoucher_wc_image_token_symbol_align').on('change input', function() {
                self.updateTokenSymbolPreview();
            });

            // Network name text style changes
            $('#univoucher_wc_image_network_name_font, #univoucher_wc_image_network_name_size, #univoucher_wc_image_network_name_color, #univoucher_wc_image_network_name_align').on('change input', function() {
                self.updateNetworkNamePreview();
            });

            // Logo height change
            $('#univoucher_wc_image_logo_height').on('change input', function() {
                self.updateLogoPreview();
            });

            // Token height change
            $('#univoucher_wc_image_token_height').on('change input', function() {
                self.updateTokenPreview();
            });

            // Test generation button
            $('#univoucher-test-generation').on('click', function(e) {
                e.preventDefault();
                self.testImageGeneration();
            });

            // Window resize handler to recalculate scale factor
            $(window).on('resize', function() {
                setTimeout(function() {
                    self.updateScaleFactor();
                    self.updateAllElementPositions();
                    self.updateAllPreviews();
                }, 100);
            });

            // Disable Enter key on editor fields
            $('#element-size, #token-symbol').on('keydown', function(e) {
                if (e.keyCode === 13) { // Enter key
                    e.preventDefault();
                    return false;
                }
            });

            // Add reset button for default templates
            this.addResetButton();
        },

        /**
         * Handle template change with confirmation
         */
        handleTemplateChange: function() {
            var self = this;
            var selectedTemplate = $('#univoucher_wc_image_template').val();
            
            // Check if custom template is selected
            if (selectedTemplate === 'custom_template') {
                this.scrollToCustomResources();
                return;
            }
            
            var confirmation = confirm('Changing the template will reset all element positions and sizes to default values. Do you want to continue?');
            
            if (confirmation) {
                // Check if it's a default template and apply appropriate reset
                if (selectedTemplate === 'UniVoucher-wide-4x3.png') {
                    this.resetToWide4x3Defaults();
                } else if (selectedTemplate === 'UniVoucher-square.png') {
                    this.resetToSquareDefaults();
                } else if (selectedTemplate === 'UniVoucher-wide-croped.png') {
                    this.resetToWideCroppedDefaults();
                } else {
                    // For non-default templates, use template change defaults
                    this.resetToTemplateChangeDefaults();
                }
                
                // Load the new template and update positions after a delay
                this.loadTemplatePreview();
                
                // Force update positions after template loads
                setTimeout(function() {
                    self.updateScaleFactor();
                    self.updateAllElementPositions();
                    self.updateAllPreviews();
                }, 200);
            } else {
                // Revert the selection to the previous value
                var currentTemplate = $('#univoucher_wc_image_template').data('previous-value') || 'UniVoucher-wide-4x3.png';
                $('#univoucher_wc_image_template').val(currentTemplate);
            }
        },

        /**
         * Scroll to custom resources section and highlight it
         */
        scrollToCustomResources: function() {
            // Find the custom resources section
            var $customResourcesSection = $('.settings-group').filter(function() {
                return $(this).find('h4').text().trim() === 'Custom Resources';
            });
            
            if ($customResourcesSection.length) {
                // Scroll to the section
                $('html, body').animate({
                    scrollTop: $customResourcesSection.offset().top - 50
                }, 500);
                
                // Highlight the section temporarily
                $customResourcesSection.css({
                    'background-color': '#f0f8ff',
                    'border': '2px solid #0073aa',
                    'border-radius': '8px'
                });
                
                // Remove highlight after 3 seconds
                setTimeout(function() {
                    $customResourcesSection.css({
                        'background-color': '',
                        'border': '',
                        'border-radius': ''
                    });
                }, 3000);
                
                // Reset template selection to previous value
                var $templateSelect = $('#univoucher_wc_image_template');
                var previousValue = $templateSelect.data('previous-value');
                if (previousValue) {
                    $templateSelect.val(previousValue);
                }
            }
        },

        /**
         * Reset to wide 4x3 template defaults
         */
        resetToWide4x3Defaults: function() {
            // Reset visibility settings
            $('#univoucher_wc_image_show_amount_with_symbol').prop('checked', true).trigger('change');
            $('#univoucher_wc_image_show_amount').prop('checked', false).trigger('change');
            $('#univoucher_wc_image_show_token_symbol').prop('checked', false).trigger('change');
            $('#univoucher_wc_image_show_network_name').prop('checked', true).trigger('change');
            $('#univoucher_wc_image_show_token_logo').prop('checked', true).trigger('change');
            $('#univoucher_wc_image_show_network_logo').prop('checked', true).trigger('change');
            
            // Reset amount with symbol settings
            $('#univoucher_wc_image_amount_with_symbol_font').val('Inter-Bold.ttf');
            $('#univoucher_wc_image_amount_with_symbol_size').val(69);
            $('#univoucher_wc_image_amount_with_symbol_color').val('#1f2937');
            $('#univoucher_wc_image_amount_with_symbol_align').val('center');
            $('#univoucher_wc_image_amount_with_symbol_x').val(411);
            $('#univoucher_wc_image_amount_with_symbol_y').val(315);
            
            // Reset amount settings
            $('#univoucher_wc_image_amount_font').val('Inter-Bold.ttf');
            $('#univoucher_wc_image_amount_size').val(20);
            $('#univoucher_wc_image_amount_color').val('#dd3333');
            $('#univoucher_wc_image_amount_align').val('right');
            $('#univoucher_wc_image_amount_x').val(53);
            $('#univoucher_wc_image_amount_y').val(21);
            
            // Reset token symbol settings
            $('#univoucher_wc_image_token_symbol_font').val('Inter-Bold.ttf');
            $('#univoucher_wc_image_token_symbol_size').val(20);
            $('#univoucher_wc_image_token_symbol_color').val('#dd3333');
            $('#univoucher_wc_image_token_symbol_align').val('left');
            $('#univoucher_wc_image_token_symbol_x').val(33);
            $('#univoucher_wc_image_token_symbol_y').val(48);
            
            // Reset network name settings
            $('#univoucher_wc_image_network_name_font').val('Inter-Bold.ttf');
            $('#univoucher_wc_image_network_name_size').val(27);
            $('#univoucher_wc_image_network_name_color').val('#1f2937');
            $('#univoucher_wc_image_network_name_align').val('left');
            $('#univoucher_wc_image_network_name_x').val(147);
            $('#univoucher_wc_image_network_name_y').val(452);
            
            // Reset logo settings
            $('#univoucher_wc_image_logo_height').val(33);
            $('#univoucher_wc_image_logo_x').val(125);
            $('#univoucher_wc_image_logo_y').val(452);
            
            // Reset token settings
            $('#univoucher_wc_image_token_height').val(68);
            $('#univoucher_wc_image_token_x').val(649);
            $('#univoucher_wc_image_token_y').val(177);
            
            // Update color pickers
            $('#univoucher_wc_image_amount_with_symbol_color').wpColorPicker('color', '#1f2937');
            $('#univoucher_wc_image_amount_color').wpColorPicker('color', '#dd3333');
            $('#univoucher_wc_image_token_symbol_color').wpColorPicker('color', '#dd3333');
            $('#univoucher_wc_image_network_name_color').wpColorPicker('color', '#1f2937');
            
            // Update all previews and positions
            this.updateAllElementPositions();
            this.updateAllPreviews();
        },

        /**
         * Reset to square template defaults
         */
        resetToSquareDefaults: function() {
            // Reset visibility settings
            $('#univoucher_wc_image_show_amount_with_symbol').prop('checked', true).trigger('change');
            $('#univoucher_wc_image_show_amount').prop('checked', false).trigger('change');
            $('#univoucher_wc_image_show_token_symbol').prop('checked', false).trigger('change');
            $('#univoucher_wc_image_show_network_name').prop('checked', true).trigger('change');
            $('#univoucher_wc_image_show_token_logo').prop('checked', true).trigger('change');
            $('#univoucher_wc_image_show_network_logo').prop('checked', true).trigger('change');
            
            // Reset amount with symbol settings
            $('#univoucher_wc_image_amount_with_symbol_font').val('Inter-Bold.ttf');
            $('#univoucher_wc_image_amount_with_symbol_size').val(69);
            $('#univoucher_wc_image_amount_with_symbol_color').val('#1f2937');
            $('#univoucher_wc_image_amount_with_symbol_align').val('center');
            $('#univoucher_wc_image_amount_with_symbol_x').val(410);
            $('#univoucher_wc_image_amount_with_symbol_y').val(413);
            
            // Reset amount settings
            $('#univoucher_wc_image_amount_font').val('Inter-Bold.ttf');
            $('#univoucher_wc_image_amount_size').val(20);
            $('#univoucher_wc_image_amount_color').val('#dd3333');
            $('#univoucher_wc_image_amount_align').val('right');
            $('#univoucher_wc_image_amount_x').val(59);
            $('#univoucher_wc_image_amount_y').val(19);
            
            // Reset token symbol settings
            $('#univoucher_wc_image_token_symbol_font').val('Inter-Bold.ttf');
            $('#univoucher_wc_image_token_symbol_size').val(20);
            $('#univoucher_wc_image_token_symbol_color').val('#dd3333');
            $('#univoucher_wc_image_token_symbol_align').val('left');
            $('#univoucher_wc_image_token_symbol_x').val(9);
            $('#univoucher_wc_image_token_symbol_y').val(47);
            
            // Reset network name settings
            $('#univoucher_wc_image_network_name_font').val('Inter-Bold.ttf');
            $('#univoucher_wc_image_network_name_size').val(32);
            $('#univoucher_wc_image_network_name_color').val('#1f2937');
            $('#univoucher_wc_image_network_name_align').val('left');
            $('#univoucher_wc_image_network_name_x').val(150);
            $('#univoucher_wc_image_network_name_y').val(555);
            
            // Reset logo settings
            $('#univoucher_wc_image_logo_height').val(33);
            $('#univoucher_wc_image_logo_x').val(125);
            $('#univoucher_wc_image_logo_y').val(554);
            
            // Reset token settings
            $('#univoucher_wc_image_token_height').val(68);
            $('#univoucher_wc_image_token_x').val(647);
            $('#univoucher_wc_image_token_y').val(276);
            
            // Update color pickers
            $('#univoucher_wc_image_amount_with_symbol_color').wpColorPicker('color', '#1f2937');
            $('#univoucher_wc_image_amount_color').wpColorPicker('color', '#dd3333');
            $('#univoucher_wc_image_token_symbol_color').wpColorPicker('color', '#dd3333');
            $('#univoucher_wc_image_network_name_color').wpColorPicker('color', '#1f2937');
            
            // Update all previews and positions
            this.updateAllElementPositions();
            this.updateAllPreviews();
        },

        /**
         * Reset to wide cropped template defaults
         */
        resetToWideCroppedDefaults: function() {
            // Reset visibility settings
            $('#univoucher_wc_image_show_amount_with_symbol').prop('checked', true).trigger('change');
            $('#univoucher_wc_image_show_amount').prop('checked', false).trigger('change');
            $('#univoucher_wc_image_show_token_symbol').prop('checked', false).trigger('change');
            $('#univoucher_wc_image_show_network_name').prop('checked', true).trigger('change');
            $('#univoucher_wc_image_show_token_logo').prop('checked', true).trigger('change');
            $('#univoucher_wc_image_show_network_logo').prop('checked', true).trigger('change');
            
            // Reset amount with symbol settings
            $('#univoucher_wc_image_amount_with_symbol_font').val('Inter-Bold.ttf');
            $('#univoucher_wc_image_amount_with_symbol_size').val(79);
            $('#univoucher_wc_image_amount_with_symbol_color').val('#1f2937');
            $('#univoucher_wc_image_amount_with_symbol_align').val('center');
            $('#univoucher_wc_image_amount_with_symbol_x').val(400);
            $('#univoucher_wc_image_amount_with_symbol_y').val(265);
            
            // Reset amount settings
            $('#univoucher_wc_image_amount_font').val('Inter-Bold.ttf');
            $('#univoucher_wc_image_amount_size').val(24);
            $('#univoucher_wc_image_amount_color').val('#dd3333');
            $('#univoucher_wc_image_amount_align').val('right');
            $('#univoucher_wc_image_amount_x').val(61);
            $('#univoucher_wc_image_amount_y').val(33);
            
            // Reset token symbol settings
            $('#univoucher_wc_image_token_symbol_font').val('Inter-Bold.ttf');
            $('#univoucher_wc_image_token_symbol_size').val(24);
            $('#univoucher_wc_image_token_symbol_color').val('#dd3333');
            $('#univoucher_wc_image_token_symbol_align').val('left');
            $('#univoucher_wc_image_token_symbol_x').val(15);
            $('#univoucher_wc_image_token_symbol_y').val(62);
            
            // Reset network name settings
            $('#univoucher_wc_image_network_name_font').val('Inter-Bold.ttf');
            $('#univoucher_wc_image_network_name_size').val(36);
            $('#univoucher_wc_image_network_name_color').val('#1f2937');
            $('#univoucher_wc_image_network_name_align').val('left');
            $('#univoucher_wc_image_network_name_x').val(99);
            $('#univoucher_wc_image_network_name_y').val(425);
            
            // Reset logo settings
            $('#univoucher_wc_image_logo_height').val(38);
            $('#univoucher_wc_image_logo_x').val(72);
            $('#univoucher_wc_image_logo_y').val(424);
            
            // Reset token settings
            $('#univoucher_wc_image_token_height').val(69);
            $('#univoucher_wc_image_token_x').val(693);
            $('#univoucher_wc_image_token_y').val(91);
            
            // Update color pickers
            $('#univoucher_wc_image_amount_with_symbol_color').wpColorPicker('color', '#1f2937');
            $('#univoucher_wc_image_amount_color').wpColorPicker('color', '#dd3333');
            $('#univoucher_wc_image_token_symbol_color').wpColorPicker('color', '#dd3333');
            $('#univoucher_wc_image_network_name_color').wpColorPicker('color', '#1f2937');
            
            // Update all previews and positions
            this.updateAllElementPositions();
            this.updateAllPreviews();
        },

        /**
         * Reset to template change defaults (all elements visible)
         */
        resetToTemplateChangeDefaults: function() {
            // Reset visibility settings - all visible
            $('#univoucher_wc_image_show_amount_with_symbol').prop('checked', true).trigger('change');
            $('#univoucher_wc_image_show_amount').prop('checked', true).trigger('change');
            $('#univoucher_wc_image_show_token_symbol').prop('checked', true).trigger('change');
            $('#univoucher_wc_image_show_network_name').prop('checked', true).trigger('change');
            $('#univoucher_wc_image_show_token_logo').prop('checked', true).trigger('change');
            $('#univoucher_wc_image_show_network_logo').prop('checked', true).trigger('change');
            
            // Reset amount with symbol settings
            $('#univoucher_wc_image_amount_with_symbol_size').val(40);
            $('#univoucher_wc_image_amount_with_symbol_color').val('#dd3333');
            $('#univoucher_wc_image_amount_with_symbol_align').val('center');
            $('#univoucher_wc_image_amount_with_symbol_x').val(112);
            $('#univoucher_wc_image_amount_with_symbol_y').val(147);
            
            // Reset amount settings
            $('#univoucher_wc_image_amount_size').val(40);
            $('#univoucher_wc_image_amount_color').val('#dd3333');
            $('#univoucher_wc_image_amount_align').val('right');
            $('#univoucher_wc_image_amount_x').val(92);
            $('#univoucher_wc_image_amount_y').val(41);
            
            // Reset token symbol settings
            $('#univoucher_wc_image_token_symbol_size').val(40);
            $('#univoucher_wc_image_token_symbol_color').val('#dd3333');
            $('#univoucher_wc_image_token_symbol_align').val('left');
            $('#univoucher_wc_image_token_symbol_x').val(17);
            $('#univoucher_wc_image_token_symbol_y').val(94);
            
            // Reset network name settings
            $('#univoucher_wc_image_network_name_size').val(40);
            $('#univoucher_wc_image_network_name_color').val('#dd3333');
            $('#univoucher_wc_image_network_name_align').val('left');
            $('#univoucher_wc_image_network_name_x').val(14);
            $('#univoucher_wc_image_network_name_y').val(194);
            
            // Reset logo settings
            $('#univoucher_wc_image_logo_height').val(50);
            $('#univoucher_wc_image_logo_x').val(130);
            $('#univoucher_wc_image_logo_y').val(42);
            
            // Reset token settings
            $('#univoucher_wc_image_token_height').val(50);
            $('#univoucher_wc_image_token_x').val(162);
            $('#univoucher_wc_image_token_y').val(100);
            
            // Update color pickers
            $('#univoucher_wc_image_amount_with_symbol_color').wpColorPicker('color', '#dd3333');
            $('#univoucher_wc_image_amount_color').wpColorPicker('color', '#dd3333');
            $('#univoucher_wc_image_token_symbol_color').wpColorPicker('color', '#dd3333');
            $('#univoucher_wc_image_network_name_color').wpColorPicker('color', '#dd3333');
            
            // Update all previews and positions
            this.updateAllElementPositions();
            this.updateAllPreviews();
        },

        /**
         * Load template preview image
         */
        loadTemplatePreview: function() {
            var selectedTemplate = $('#univoucher_wc_image_template').val();
            if (!selectedTemplate) {
                return;
            }
            
            var templateUrl = univoucher_image_templates_ajax.templates_url + selectedTemplate;
            var $preview = $('#univoucher-template-preview');
            var self = this;
            
            // Remove any existing load handlers
            $preview.off('load error');
            
            // Add load handler
            $preview.on('load', function() {
                // Wait for the image to be fully rendered
                setTimeout(function() {
                    // Update scale factor with actual image dimensions
                    self.updateScaleFactor();
                    
                    // Update all positions and previews
                    self.updateAllElementPositions();
                    self.updateAllPreviews();
                }, 150);
            });
            
            // Handle image load errors
            $preview.on('error', function() {
                console.error('Failed to load template image:', templateUrl);
            });
            
            $preview.attr('src', templateUrl);
        },

        /**
         * Update scale factor and original dimensions based on preview image
         */
        updateScaleFactor: function() {
            var $preview = $('#univoucher-template-preview');
            var displayWidth = $preview.width();
            var displayHeight = $preview.height();
            
            // Get the actual/natural dimensions of the image
            var img = $preview[0];
            if (img.naturalWidth && img.naturalHeight) {
                this.originalWidth = img.naturalWidth;
                this.originalHeight = img.naturalHeight;
            }
            
            if (displayWidth > 0 && this.originalWidth > 0) {
                this.scaleFactor = displayWidth / this.originalWidth;
            }
        },

        /**
         * Update all element positions
         */
        updateAllElementPositions: function() {
            this.updateAmountWithSymbolPosition();
            this.updateAmountPosition();
            this.updateTokenSymbolPosition();
            this.updateNetworkNamePosition();
            this.updateLogoPosition();
            this.updateTokenPosition();
        },

        /**
         * Update all element previews
         */
        updateAllPreviews: function() {
            this.updateAmountWithSymbolPreview();
            this.updateAmountPreview();
            this.updateTokenSymbolPreview();
            this.updateNetworkNamePreview();
            this.updateLogoPreview();
            this.updateTokenPreview();
        },

        /**
         * Update amount with symbol element position from stored coordinates
         */
        updateAmountWithSymbolPosition: function() {
            var storedX = parseInt($('#univoucher_wc_image_amount_with_symbol_x').val(), 10);
            var storedY = parseInt($('#univoucher_wc_image_amount_with_symbol_y').val(), 10);
            var alignment = $('#univoucher_wc_image_amount_with_symbol_align').val() || 'center';
            
            // Convert stored coordinates to display coordinates
            var displayX = storedX * this.scaleFactor;
            var displayY = storedY * this.scaleFactor;
            
            // Position element based on alignment setting
            var $element = $('#univoucher-draggable-amount-with-symbol');
            var width = $element.outerWidth();
            var height = $element.outerHeight();
            
            var left, top;
            
            switch(alignment) {
                case 'left':
                    // Reference point is at the left edge
                    left = displayX;
                    break;
                case 'right':
                    // Reference point is at the right edge
                    left = displayX - width;
                    break;
                case 'center':
                default:
                    // Reference point is at the center
                    left = displayX - (width / 2);
                    break;
            }
            
            // Y is always center-aligned
            top = displayY - (height / 2);
            
            $element.css({
                left: left,
                top: top
            });
        },

        /**
         * Update amount element position from stored coordinates
         */
        updateAmountPosition: function() {
            var storedX = parseInt($('#univoucher_wc_image_amount_x').val(), 10);
            var storedY = parseInt($('#univoucher_wc_image_amount_y').val(), 10);
            var alignment = $('#univoucher_wc_image_amount_align').val() || 'center';
            
            // Convert stored coordinates to display coordinates
            var displayX = storedX * this.scaleFactor;
            var displayY = storedY * this.scaleFactor;
            
            // Position element based on alignment setting
            var $element = $('#univoucher-draggable-amount');
            var width = $element.outerWidth();
            var height = $element.outerHeight();
            
            var left, top;
            
            switch(alignment) {
                case 'left':
                    // Reference point is at the left edge
                    left = displayX;
                    break;
                case 'right':
                    // Reference point is at the right edge
                    left = displayX - width;
                    break;
                case 'center':
                default:
                    // Reference point is at the center
                    left = displayX - (width / 2);
                    break;
            }
            
            // Y is always center-aligned
            top = displayY - (height / 2);
            
            $element.css({
                left: left,
                top: top
            });
        },

        /**
         * Update token symbol element position from stored coordinates
         */
        updateTokenSymbolPosition: function() {
            var storedX = parseInt($('#univoucher_wc_image_token_symbol_x').val(), 10);
            var storedY = parseInt($('#univoucher_wc_image_token_symbol_y').val(), 10);
            var alignment = $('#univoucher_wc_image_token_symbol_align').val() || 'center';
            
            // Convert stored coordinates to display coordinates
            var displayX = storedX * this.scaleFactor;
            var displayY = storedY * this.scaleFactor;
            
            // Position element based on alignment setting
            var $element = $('#univoucher-draggable-token-symbol');
            var width = $element.outerWidth();
            var height = $element.outerHeight();
            
            var left, top;
            
            switch(alignment) {
                case 'left':
                    // Reference point is at the left edge
                    left = displayX;
                    break;
                case 'right':
                    // Reference point is at the right edge
                    left = displayX - width;
                    break;
                case 'center':
                default:
                    // Reference point is at the center
                    left = displayX - (width / 2);
                    break;
            }
            
            // Y is always center-aligned
            top = displayY - (height / 2);
            
            $element.css({
                left: left,
                top: top
            });
        },

        /**
         * Update network name element position from stored coordinates
         */
        updateNetworkNamePosition: function() {
            var storedX = parseInt($('#univoucher_wc_image_network_name_x').val(), 10);
            var storedY = parseInt($('#univoucher_wc_image_network_name_y').val(), 10);
            var alignment = $('#univoucher_wc_image_network_name_align').val() || 'center';
            
            // Convert stored coordinates to display coordinates
            var displayX = storedX * this.scaleFactor;
            var displayY = storedY * this.scaleFactor;
            
            // Position element based on alignment setting
            var $element = $('#univoucher-draggable-network-name');
            var width = $element.outerWidth();
            var height = $element.outerHeight();
            
            var left, top;
            
            switch(alignment) {
                case 'left':
                    // Reference point is at the left edge
                    left = displayX;
                    break;
                case 'right':
                    // Reference point is at the right edge
                    left = displayX - width;
                    break;
                case 'center':
                default:
                    // Reference point is at the center
                    left = displayX - (width / 2);
                    break;
            }
            
            // Y is always center-aligned
            top = displayY - (height / 2);
            
            $element.css({
                left: left,
                top: top
            });
        },

        /**
         * Update logo element position from stored coordinates
         */
        updateLogoPosition: function() {
            var storedX = parseInt($('#univoucher_wc_image_logo_x').val(), 10);
            var storedY = parseInt($('#univoucher_wc_image_logo_y').val(), 10);
            
            // Convert stored coordinates to display coordinates
            var displayX = storedX * this.scaleFactor;
            var displayY = storedY * this.scaleFactor;
            
            // Center the logo element on the coordinates
            var $logoElement = $('#univoucher-draggable-logo');
            var logoWidth = $logoElement.outerWidth();
            var logoHeight = $logoElement.outerHeight();
            
            $logoElement.css({
                left: displayX - (logoWidth / 2),
                top: displayY - (logoHeight / 2)
            });
        },

        /**
         * Update text position inputs based on draggable position (for individual text elements)
         */
        updateTextPositionInputs: function(position, element) {
            // Use the passed element or try to find it from the draggable context
            var $element = element ? $(element) : $(this);
            var width = $element.outerWidth() || 50; // fallback width
            var height = $element.outerHeight() || 20; // fallback height
            
            // Determine which text element is being dragged
            var elementId = $element.attr('id');
            var elementType = '';
            var alignment = 'center'; // default
            
            if (elementId === 'univoucher-draggable-amount-with-symbol') {
                elementType = 'amount_with_symbol';
                alignment = $('#univoucher_wc_image_amount_with_symbol_align').val() || 'center';
            } else if (elementId === 'univoucher-draggable-amount') {
                elementType = 'amount';
                alignment = $('#univoucher_wc_image_amount_align').val() || 'center';
            } else if (elementId === 'univoucher-draggable-token-symbol') {
                elementType = 'token_symbol';
                alignment = $('#univoucher_wc_image_token_symbol_align').val() || 'center';
            } else if (elementId === 'univoucher-draggable-network-name') {
                elementType = 'network_name';
                alignment = $('#univoucher_wc_image_network_name_align').val() || 'center';
            }
            
            // Calculate reference point based on alignment (this matches how backend interprets coordinates)
            var referenceX, referenceY;
            referenceY = position.top + (height / 2); // Y is always center for vertical alignment
            
            switch(alignment) {
                case 'left':
                    // For left alignment, reference point is at the left edge of the text
                    referenceX = position.left;
                    break;
                case 'right':
                    // For right alignment, reference point is at the right edge of the text
                    referenceX = position.left + width;
                    break;
                case 'center':
                default:
                    // For center alignment, reference point is at the center of the text
                    referenceX = position.left + (width / 2);
                    break;
            }
            
            // Convert to original image coordinates
            var originalX = Math.round(referenceX / UniVoucherImageTemplates.scaleFactor);
            var originalY = Math.round(referenceY / UniVoucherImageTemplates.scaleFactor);
            
            // Update the specific position inputs based on element type
            if (elementType === 'amount_with_symbol') {
                $('#univoucher_wc_image_amount_with_symbol_x').val(originalX);
                $('#univoucher_wc_image_amount_with_symbol_y').val(originalY);
            } else if (elementType === 'amount') {
                $('#univoucher_wc_image_amount_x').val(originalX);
                $('#univoucher_wc_image_amount_y').val(originalY);
            } else if (elementType === 'token_symbol') {
                $('#univoucher_wc_image_token_symbol_x').val(originalX);
                $('#univoucher_wc_image_token_symbol_y').val(originalY);
            } else if (elementType === 'network_name') {
                $('#univoucher_wc_image_network_name_x').val(originalX);
                $('#univoucher_wc_image_network_name_y').val(originalY);
            }
        },

        /**
         * Update logo position inputs based on draggable position
         */
        updateLogoPositionInputs: function(position) {
            var $logoElement = $('#univoucher-draggable-logo');
            var logoWidth = $logoElement.outerWidth();
            var logoHeight = $logoElement.outerHeight();
            
            // Calculate center coordinates
            var centerX = position.left + (logoWidth / 2);
            var centerY = position.top + (logoHeight / 2);
            
            // Convert to original image coordinates
            var originalX = Math.round(centerX / this.scaleFactor);
            var originalY = Math.round(centerY / this.scaleFactor);
            
            // Update hidden inputs
            $('#univoucher_wc_image_logo_x').val(originalX);
            $('#univoucher_wc_image_logo_y').val(originalY);
        },

        /**
         * Update token element position from stored coordinates
         */
        updateTokenPosition: function() {
            var storedX = parseInt($('#univoucher_wc_image_token_x').val(), 10);
            var storedY = parseInt($('#univoucher_wc_image_token_y').val(), 10);
            
            // Convert stored coordinates to display coordinates
            var displayX = storedX * this.scaleFactor;
            var displayY = storedY * this.scaleFactor;
            
            // Center the token element on the coordinates
            var $tokenElement = $('#univoucher-draggable-token');
            var tokenWidth = $tokenElement.outerWidth();
            var tokenHeight = $tokenElement.outerHeight();
            
            $tokenElement.css({
                left: displayX - (tokenWidth / 2),
                top: displayY - (tokenHeight / 2)
            });
        },

        /**
         * Update token position inputs based on draggable position
         */
        updateTokenPositionInputs: function(position) {
            var $tokenElement = $('#univoucher-draggable-token');
            var tokenWidth = $tokenElement.outerWidth();
            var tokenHeight = $tokenElement.outerHeight();
            
            // Calculate center coordinates
            var centerX = position.left + (tokenWidth / 2);
            var centerY = position.top + (tokenHeight / 2);
            
            // Convert to original image coordinates
            var originalX = Math.round(centerX / this.scaleFactor);
            var originalY = Math.round(centerY / this.scaleFactor);
            
            // Update hidden inputs
            $('#univoucher_wc_image_token_x').val(originalX);
            $('#univoucher_wc_image_token_y').val(originalY);
        },

        /**
         * Update amount with symbol preview styling
         */
        updateAmountWithSymbolPreview: function() {
            var $element = $('#univoucher-draggable-amount-with-symbol');
            var $colorField = $('#univoucher_wc_image_amount_with_symbol_color');
            
            // Get color value - try multiple methods to ensure we get the current color
            var color = $colorField.val();
            if (!color || color === '') {
                try {
                    color = $colorField.wpColorPicker('color');
                } catch(e) {
                    color = '#000000';
                }
            }
            if (!color || color === '') {
                color = '#000000';
            }
            
            var size = $('#univoucher_wc_image_amount_with_symbol_size').val() || 20;
            var font = $('#univoucher_wc_image_amount_with_symbol_font').val() || 'Inter-Bold.ttf';
            
            // Load the actual font and apply it
            var fontFamily = this.loadActualFont(font);
            
            // Apply styles to preview text
            $element.css({
                color: color,
                fontSize: (size * this.scaleFactor) + 'px',
                fontFamily: fontFamily
            });
        },

        /**
         * Update amount preview styling
         */
        updateAmountPreview: function() {
            var $element = $('#univoucher-draggable-amount');
            var $colorField = $('#univoucher_wc_image_amount_color');
            
            // Get color value - try multiple methods to ensure we get the current color
            var color = $colorField.val();
            if (!color || color === '') {
                try {
                    color = $colorField.wpColorPicker('color');
                } catch(e) {
                    color = '#000000';
                }
            }
            if (!color || color === '') {
                color = '#000000';
            }
            
            var size = $('#univoucher_wc_image_amount_size').val() || 20;
            var font = $('#univoucher_wc_image_amount_font').val() || 'Inter-Bold.ttf';
            
            // Load the actual font and apply it
            var fontFamily = this.loadActualFont(font);
            
            // Apply styles to preview text
            $element.css({
                color: color,
                fontSize: (size * this.scaleFactor) + 'px',
                fontFamily: fontFamily
            });
        },

        /**
         * Update token symbol preview styling
         */
        updateTokenSymbolPreview: function() {
            var $element = $('#univoucher-draggable-token-symbol');
            var $colorField = $('#univoucher_wc_image_token_symbol_color');
            
            // Get color value - try multiple methods to ensure we get the current color
            var color = $colorField.val();
            if (!color || color === '') {
                try {
                    color = $colorField.wpColorPicker('color');
                } catch(e) {
                    color = '#000000';
                }
            }
            if (!color || color === '') {
                color = '#000000';
            }
            
            var size = $('#univoucher_wc_image_token_symbol_size').val() || 16;
            var font = $('#univoucher_wc_image_token_symbol_font').val() || 'Inter-Bold.ttf';
            
            // Load the actual font and apply it
            var fontFamily = this.loadActualFont(font);
            
            // Apply styles to preview text
            $element.css({
                color: color,
                fontSize: (size * this.scaleFactor) + 'px',
                fontFamily: fontFamily
            });
        },

        /**
         * Update network name preview styling
         */
        updateNetworkNamePreview: function() {
            var $element = $('#univoucher-draggable-network-name');
            var $colorField = $('#univoucher_wc_image_network_name_color');
            
            // Get color value - try multiple methods to ensure we get the current color
            var color = $colorField.val();
            if (!color || color === '') {
                try {
                    color = $colorField.wpColorPicker('color');
                } catch(e) {
                    color = '#000000';
                }
            }
            if (!color || color === '') {
                color = '#000000';
            }
            
            var size = $('#univoucher_wc_image_network_name_size').val() || 14;
            var font = $('#univoucher_wc_image_network_name_font').val() || 'Inter-Bold.ttf';
            
            // Load the actual font and apply it
            var fontFamily = this.loadActualFont(font);
            
            // Apply styles to preview text
            $element.css({
                color: color,
                fontSize: (size * this.scaleFactor) + 'px',
                fontFamily: fontFamily
            });
        },

        /**
         * Update logo preview styling
         */
        updateLogoPreview: function() {
            var $logoElement = $('#univoucher-draggable-logo img');
            var $logoContainer = $('#univoucher-draggable-logo');
            var logoHeight = parseInt($('#univoucher_wc_image_logo_height').val(), 10);
            var self = this;
            
            // Apply scaled height to preview logo
            var scaledHeight = logoHeight * this.scaleFactor;
            
            // Calculate width maintaining aspect ratio
            var naturalWidth = $logoElement[0].naturalWidth;
            var naturalHeight = $logoElement[0].naturalHeight;
            
            if (naturalWidth && naturalHeight) {
                var aspectRatio = naturalWidth / naturalHeight;
                var scaledWidth = scaledHeight * aspectRatio;
                
                $logoElement.css({
                    height: scaledHeight + 'px',
                    width: scaledWidth + 'px'
                });
                
                // Update container size to match
                $logoContainer.css({
                    width: scaledWidth + 'px',
                    height: scaledHeight + 'px'
                });
                
                // Recalculate position after size change
                setTimeout(function() {
                    self.updateLogoPosition();
                }, 10);
            } else {
                // Fallback if natural dimensions aren't available yet
                $logoElement.css({
                    height: scaledHeight + 'px',
                    width: 'auto'
                });
            }
        },

        /**
         * Update token preview styling
         */
        updateTokenPreview: function() {
            var $tokenElement = $('#univoucher-draggable-token img');
            var $tokenContainer = $('#univoucher-draggable-token');
            var tokenHeight = parseInt($('#univoucher_wc_image_token_height').val(), 10);
            var self = this;
            
            // Apply scaled height to preview token
            var scaledHeight = tokenHeight * this.scaleFactor;
            
            // Calculate width maintaining aspect ratio
            var naturalWidth = $tokenElement[0].naturalWidth;
            var naturalHeight = $tokenElement[0].naturalHeight;
            
            if (naturalWidth && naturalHeight) {
                var aspectRatio = naturalWidth / naturalHeight;
                var scaledWidth = scaledHeight * aspectRatio;
                
                $tokenElement.css({
                    height: scaledHeight + 'px',
                    width: scaledWidth + 'px'
                });
                
                // Update container size to match
                $tokenContainer.css({
                    width: scaledWidth + 'px',
                    height: scaledHeight + 'px'
                });
                
                // Recalculate position after size change
                setTimeout(function() {
                    self.updateTokenPosition();
                }, 10);
            } else {
                // Fallback if natural dimensions aren't available yet
                $tokenElement.css({
                    height: scaledHeight + 'px',
                    width: 'auto'
                });
            }
        },

        /**
         * Load actual font file from server
         */
        loadActualFont: function(fontFilename) {
            if (!fontFilename) {
                return '';
            }
            
            // Create unique font family name
            var fontFamilyName = 'UniVoucherFont_' + fontFilename.replace(/[^a-zA-Z0-9]/g, '_');
            
            // Check if font is already loaded
            if (this.loadedFonts && this.loadedFonts[fontFilename]) {
                return fontFamilyName;
            }
            
            // Initialize loaded fonts tracker
            if (!this.loadedFonts) {
                this.loadedFonts = {};
            }
            
            // Get font URL
            var fontUrl = univoucher_image_templates_ajax.plugin_url + '/admin/fonts/' + fontFilename;
            
            // Create and inject @font-face CSS
            var fontFaceCSS = '@font-face { ' +
                'font-family: "' + fontFamilyName + '"; ' +
                'src: url("' + fontUrl + '") format("truetype"); ' +
                'font-weight: bold; ' +
                'font-display: swap; ' +
            '}';
            
            // Check if style element exists, create if not
            var styleId = 'univoucher-font-styles';
            var styleElement = document.getElementById(styleId);
            if (!styleElement) {
                styleElement = document.createElement('style');
                styleElement.id = styleId;
                document.head.appendChild(styleElement);
            }
            
            // Add the font face rule
            styleElement.textContent += fontFaceCSS;
            
            // Mark font as loaded
            this.loadedFonts[fontFilename] = true;
            
            return fontFamilyName;
        },

        /**
         * Test image generation with current settings
         */
        testImageGeneration: function() {
            var $button = $('#univoucher-test-generation');
            var $spinner = $('#univoucher-test-spinner');
            var $result = $('#univoucher-test-result');
            
            // Show loading state
            $button.prop('disabled', true).text('Generating Test Images...');
            $spinner.addClass('is-active');
            $result.html('');
            
            // Collect current settings
            var data = {
                action: 'univoucher_test_image_generation',
                nonce: univoucher_image_templates_ajax.nonce,
                template: $('#univoucher_wc_image_template').val(),
                
                // Visibility settings
                show_amount_with_symbol: $('#univoucher_wc_image_show_amount_with_symbol').is(':checked') ? 1 : 0,
                show_amount: $('#univoucher_wc_image_show_amount').is(':checked') ? 1 : 0,
                show_token_symbol: $('#univoucher_wc_image_show_token_symbol').is(':checked') ? 1 : 0,
                show_network_name: $('#univoucher_wc_image_show_network_name').is(':checked') ? 1 : 0,
                show_token_logo: $('#univoucher_wc_image_show_token_logo').is(':checked') ? 1 : 0,
                show_network_logo: $('#univoucher_wc_image_show_network_logo').is(':checked') ? 1 : 0,
                
                // Amount with symbol text settings
                amount_with_symbol_font: $('#univoucher_wc_image_amount_with_symbol_font').val(),
                amount_with_symbol_size: $('#univoucher_wc_image_amount_with_symbol_size').val(),
                amount_with_symbol_color: $('#univoucher_wc_image_amount_with_symbol_color').val(),
                amount_with_symbol_align: $('#univoucher_wc_image_amount_with_symbol_align').val(),
                amount_with_symbol_x: $('#univoucher_wc_image_amount_with_symbol_x').val(),
                amount_with_symbol_y: $('#univoucher_wc_image_amount_with_symbol_y').val(),
                
                // Amount text settings
                amount_font: $('#univoucher_wc_image_amount_font').val(),
                amount_size: $('#univoucher_wc_image_amount_size').val(),
                amount_color: $('#univoucher_wc_image_amount_color').val(),
                amount_align: $('#univoucher_wc_image_amount_align').val(),
                amount_x: $('#univoucher_wc_image_amount_x').val(),
                amount_y: $('#univoucher_wc_image_amount_y').val(),
                
                // Token symbol text settings
                token_symbol_font: $('#univoucher_wc_image_token_symbol_font').val(),
                token_symbol_size: $('#univoucher_wc_image_token_symbol_size').val(),
                token_symbol_color: $('#univoucher_wc_image_token_symbol_color').val(),
                token_symbol_align: $('#univoucher_wc_image_token_symbol_align').val(),
                token_symbol_x: $('#univoucher_wc_image_token_symbol_x').val(),
                token_symbol_y: $('#univoucher_wc_image_token_symbol_y').val(),
                
                // Network name text settings
                network_name_font: $('#univoucher_wc_image_network_name_font').val(),
                network_name_size: $('#univoucher_wc_image_network_name_size').val(),
                network_name_color: $('#univoucher_wc_image_network_name_color').val(),
                network_name_align: $('#univoucher_wc_image_network_name_align').val(),
                network_name_x: $('#univoucher_wc_image_network_name_x').val(),
                network_name_y: $('#univoucher_wc_image_network_name_y').val(),
                
                // Logo settings
                logo_height: $('#univoucher_wc_image_logo_height').val(),
                logo_x: $('#univoucher_wc_image_logo_x').val(),
                logo_y: $('#univoucher_wc_image_logo_y').val(),
                token_height: $('#univoucher_wc_image_token_height').val(),
                token_x: $('#univoucher_wc_image_token_x').val(),
                token_y: $('#univoucher_wc_image_token_y').val()
            };
            
            // Make AJAX request
            $.ajax({
                url: univoucher_image_templates_ajax.ajax_url,
                type: 'POST',
                data: data,
                success: function(response) {
                    if (response.success) {
                        var html = '<div style="border: 1px solid #00a32a; border-radius: 4px; padding: 15px; background: #f0f8f0;">' +
                            '<h4 style="margin: 0 0 15px 0; color: #00a32a;">✓ Test Generation Successful</h4>' +
                            '<p style="margin: 0 0 15px 0; font-size: 12px; color: #666;">Here are 6 test variations showing how your gift card images will look with different tokens and networks:</p>' +
                            '<div style="display: grid; grid-template-columns: 1fr 1fr 1fr; gap: 20px; max-width: 100%;">';
                        
                        // Generate HTML for each test image
                        if (response.data.images && response.data.images.length > 0) {
                            response.data.images.forEach(function(imageData, index) {
                                html += '<div style="text-align: center; background: #fff; padding: 15px; border-radius: 8px; border: 1px solid #ddd; box-shadow: 0 2px 4px rgba(0,0,0,0.1);">' +
                                    '<img src="' + imageData.image_data + '" alt="Test variation ' + (index + 1) + '" style="max-width: 100%; height: auto; border: 1px solid #ccc; border-radius: 4px;" />' +
                                    '</div>';
                            });
                        }
                        
                        html += '</div></div>';
                        $result.html(html);
                    } else {
                        $result.html(
                            '<div style="border: 1px solid #d63638; border-radius: 4px; padding: 10px; background: #fbeaea; color: #d63638;">' +
                            '<strong>Error:</strong> ' + (response.data.message || 'Unknown error occurred.') +
                            '</div>'
                        );
                    }
                },
                error: function() {
                    $result.html(
                        '<div style="border: 1px solid #d63638; border-radius: 4px; padding: 10px; background: #fbeaea; color: #d63638;">' +
                        '<strong>Error:</strong> Failed to generate test image. Please try again.' +
                        '</div>'
                    );
                },
                complete: function() {
                    // Reset button state
                    $button.prop('disabled', false).text('Generate Test Variations');
                    $spinner.removeClass('is-active');
                }
            });
        },

        /**
         * Select an element and load its settings into the controllers
         */
        selectElement: function(element, type) {
            var $element = $(element);
            
            // Store selected element info
            this.selectedElement = $element;
            this.selectedElementType = type;

            // Activate the element (this will handle all visual states)
            this.activateElement(element, type);

            // Hide help text and remove highlighting
            $('#no-selection-help').hide().removeClass('highlighted');

            // Enable appropriate controls and load settings
            if (type === 'amount_with_symbol' || type === 'amount' || type === 'token_symbol' || type === 'network_name') {
                // Enable text controls, disable logo controls
                this.enableColorPicker();
                $('.custom-font-picker').removeClass('disabled');
                $('#element-size, #element-color, #element-align').prop('disabled', false);
    
                this.loadTextElementSettings(type);
            } else {
                // Enable logo controls, disable text controls
                this.disableColorPicker();
                $('.custom-font-picker').addClass('disabled');
                $('#element-color, #element-align').prop('disabled', true);
                $('#element-size').prop('disabled', false);

                this.loadLogoElementSettings(type);
            }
        },

        /**
         * Load text element settings into controllers
         */
        loadTextElementSettings: function(type) {
            var font, size, color, align;

            switch(type) {
                case 'amount_with_symbol':
                    font = $('#univoucher_wc_image_amount_with_symbol_font').val();
                    size = $('#univoucher_wc_image_amount_with_symbol_size').val();
                    color = $('#univoucher_wc_image_amount_with_symbol_color').val();
                    align = $('#univoucher_wc_image_amount_with_symbol_align').val();
                    break;
                case 'amount':
                    font = $('#univoucher_wc_image_amount_font').val();
                    size = $('#univoucher_wc_image_amount_size').val();
                    color = $('#univoucher_wc_image_amount_color').val();
                    align = $('#univoucher_wc_image_amount_align').val();
                    break;
                case 'token_symbol':
                    font = $('#univoucher_wc_image_token_symbol_font').val();
                    size = $('#univoucher_wc_image_token_symbol_size').val();
                    color = $('#univoucher_wc_image_token_symbol_color').val();
                    align = $('#univoucher_wc_image_token_symbol_align').val();
                    break;
                case 'network_name':
                    font = $('#univoucher_wc_image_network_name_font').val();
                    size = $('#univoucher_wc_image_network_name_size').val();
                    color = $('#univoucher_wc_image_network_name_color').val();
                    align = $('#univoucher_wc_image_network_name_align').val();
                    break;
            }

            // Update custom font picker
            var fontPicker = $('#element-font');
            fontPicker.attr('data-value', font);
            
            // Find the font name for display
            var fontName = font;
            $('.font-option').each(function() {
                if ($(this).data('value') === font) {
                    fontName = $(this).text();
                    return false;
                }
            });
            fontPicker.find('.font-picker-text').text(fontName);
            
            $('#element-size').val(size);
            $('#element-color').val(color);
            $('#element-align').val(align);
            
            // Update color picker display
            if ($('#element-color').hasClass('wp-color-picker')) {
                $('#element-color').wpColorPicker('color', color);
            }
        },

        /**
         * Load logo element settings into controllers
         */
        loadLogoElementSettings: function(type) {
            var size;

            switch(type) {
                case 'logo':
                    size = $('#univoucher_wc_image_logo_height').val();
                    break;
                case 'token':
                    size = $('#univoucher_wc_image_token_height').val();
                    break;
            }

            $('#element-size').val(size);
        },

        /**
         * Apply current controller settings to the selected element
         */
        applyElementSettings: function() {
            if (!this.selectedElement || !this.selectedElementType) {
                return;
            }

            var type = this.selectedElementType;

            if (type === 'amount_with_symbol' || type === 'amount' || type === 'token_symbol' || type === 'network_name') {
                // Update hidden inputs for text elements
                var font = $('#element-font').attr('data-value');
                var size = $('#element-size').val();
                var color = $('#element-color').wpColorPicker('color');
                var align = $('#element-align').val();

                switch(type) {
                    case 'amount_with_symbol':
                        $('#univoucher_wc_image_amount_with_symbol_font').val(font).trigger('change');
                        $('#univoucher_wc_image_amount_with_symbol_size').val(size).trigger('change');
                        $('#univoucher_wc_image_amount_with_symbol_color').val(color).trigger('change');
                        $('#univoucher_wc_image_amount_with_symbol_align').val(align).trigger('change');
                        break;
                    case 'amount':
                        $('#univoucher_wc_image_amount_font').val(font).trigger('change');
                        $('#univoucher_wc_image_amount_size').val(size).trigger('change');
                        $('#univoucher_wc_image_amount_color').val(color).trigger('change');
                        $('#univoucher_wc_image_amount_align').val(align).trigger('change');
                        break;
                    case 'token_symbol':
                        $('#univoucher_wc_image_token_symbol_font').val(font).trigger('change');
                        $('#univoucher_wc_image_token_symbol_size').val(size).trigger('change');
                        $('#univoucher_wc_image_token_symbol_color').val(color).trigger('change');
                        $('#univoucher_wc_image_token_symbol_align').val(align).trigger('change');
                        break;
                    case 'network_name':
                        $('#univoucher_wc_image_network_name_font').val(font).trigger('change');
                        $('#univoucher_wc_image_network_name_size').val(size).trigger('change');
                        $('#univoucher_wc_image_network_name_color').val(color).trigger('change');
                        $('#univoucher_wc_image_network_name_align').val(align).trigger('change');
                        break;
                }
                
                // Update alignment indicators when alignment changes
                if (this.selectedElement && this.selectedElementType) {
                    this.showAlignmentIndicators(this.selectedElement, this.selectedElementType);
                }
                
            } else {
                // Update hidden inputs for logo elements
                var size = $('#element-size').val();

                switch(type) {
                    case 'logo':
                        $('#univoucher_wc_image_logo_height').val(size).trigger('change');
                        break;
                    case 'token':
                        $('#univoucher_wc_image_token_height').val(size).trigger('change');
                        break;
                }
            }
        },

        /**
         * Deselect current element
         */
        deselectElement: function() {
            // Clear selection state
            this.selectedElement = null;
            this.selectedElementType = null;

            // Deactivate all elements
            this.deactivateElement();

            // Disable all controls
            $('.custom-font-picker').addClass('disabled');
            $('#element-size, #element-color, #element-align').prop('disabled', true);
            this.disableColorPicker();
            
            // Show help text with highlighting
            $('#no-selection-help').show().addClass('highlighted');
        },

        /**
         * Unified element activation - handles outline, resize controls, and alignment indicators
         */
        activateElement: function(element, type) {
            if (!element) return;
            
            var $element = $(element);
            var self = this;
            
            // Clear any pending timeouts
            clearTimeout(this.hideControlsTimeout);
            
            // Prevent activation if this element is already active (prevents flashing)
            if (this.currentElement && this.currentElement.is($element)) {
                return;
            }
            
            // Store current element info
            this.currentElement = $element;
            this.currentElementType = type;
            
            // Add active class for outline
            $('.draggable-element').removeClass('active');
            $element.addClass('active');
            
            // Remove existing controls and indicators cleanly
            $('.univoucher-resize-controls').remove();
            this.hideAlignmentIndicators();
            
            // Create and show resize controls
            var $controls = $('<div class="univoucher-resize-controls">' +
                '<button type="button" class="resize-btn resize-minus" title="Decrease Size">-</button>' +
                '<button type="button" class="resize-btn resize-plus" title="Increase Size">+</button>' +
            '</div>');
            
            this.positionResizeControls($controls, $element);
            $('#univoucher-image-editor').append($controls);
            
            // Show controls without delay to prevent flashing
            $controls.addClass('show');
            
            this.bindResizeEvents($controls, type);
            
            // Show alignment indicators for text elements
            if (type === 'amount_with_symbol' || type === 'amount' || type === 'token_symbol' || type === 'network_name') {
                this.showAlignmentIndicators($element, type);
            }
        },

        /**
         * Unified element deactivation
         */
        deactivateElement: function(exceptElement) {
            var self = this;
            clearTimeout(this.hideControlsTimeout);
            
            // If element is selected, keep it active
            if (this.selectedElement) {
                return;
            }
            
            this.hideControlsTimeout = setTimeout(function() {
                // Only deactivate if no element is selected
                if (!self.selectedElement) {
                    // Remove active class from all elements
                    $('.draggable-element').removeClass('active');
                    
                    // Remove controls
                    var $controls = $('.univoucher-resize-controls');
                    if ($controls.length) {
                        $controls.removeClass('show');
                        setTimeout(function() {
                            $controls.remove();
                        }, 200);
                    }
                    
                    // Hide alignment indicators
                    self.hideAlignmentIndicators();
                    
                    // Clear current element info
                    self.currentElement = null;
                    self.currentElementType = null;
                }
            }, 300); // Increased delay to prevent flashing
        },

        /**
         * Show alignment indicators for the selected text element
         */
        showAlignmentIndicators: function($element, type) {
            // Remove any existing indicators
            this.hideAlignmentIndicators();
            
            var elementPos = $element.position();
            var elementWidth = $element.outerWidth();
            var elementHeight = $element.outerHeight();
            var $container = $('#univoucher-image-editor');
            var containerHeight = $container.height();
            
            // Get current alignment setting
            var alignment = 'center'; // default
            switch(type) {
                case 'amount_with_symbol':
                    alignment = $('#univoucher_wc_image_amount_with_symbol_align').val() || 'center';
                    break;
                case 'amount':
                    alignment = $('#univoucher_wc_image_amount_align').val() || 'center';
                    break;
                case 'token_symbol':
                    alignment = $('#univoucher_wc_image_token_symbol_align').val() || 'center';
                    break;
                case 'network_name':
                    alignment = $('#univoucher_wc_image_network_name_align').val() || 'center';
                    break;
            }
            
            // Calculate the alignment reference point based on alignment setting
            var referenceX, referenceY;
            referenceY = elementPos.top + (elementHeight / 2);
            
            switch(alignment) {
                case 'left':
                    // For left alignment, reference point is at the left edge of the text
                    referenceX = elementPos.left;
                    break;
                case 'right':
                    // For right alignment, reference point is at the right edge of the text
                    referenceX = elementPos.left + elementWidth;
                    break;
                case 'center':
                default:
                    // For center alignment, reference point is at the center of the text
                    referenceX = elementPos.left + (elementWidth / 2);
                    break;
            }
            
            // Create vertical line at 30% of editor container height
            var editorHeight = $container.height();
            var lineHeight = editorHeight * 0.3;
            var lineTop = referenceY - (lineHeight / 2); // Center the line on the element
            
            var $verticalLine = $('<div class="alignment-indicator alignment-vertical-line"></div>');
            $verticalLine.css({
                left: referenceX + 'px',
                top: lineTop + 'px',
                height: lineHeight + 'px'
            });
            
            // Calculate arrow position based on alignment
            var arrowX = referenceX;
            var arrowY = referenceY;
            var arrowClass = alignment;
            var arrowLabel = alignment.charAt(0).toUpperCase() + alignment.slice(1);
            
            // Create arrow showing the draw position
            var $arrow = $('<div class="alignment-indicator alignment-arrow ' + arrowClass + '" data-label="' + arrowLabel + '"></div>');
            $arrow.css({
                left: arrowX + 'px',
                top: arrowY + 'px'
            });
            
            // Add indicators to container
            $container.append($verticalLine);
            $container.append($arrow);
            
            // Show indicators immediately to prevent flashing
            $verticalLine.addClass('show');
            $arrow.addClass('show');
            
            // Store reference for cleanup
            this.alignmentIndicators = [$verticalLine, $arrow];
        },

        /**
         * Hide alignment indicators
         */
        hideAlignmentIndicators: function() {
            // Simply remove all alignment indicators immediately
            $('.alignment-indicator').remove();
            
            // Clear stored reference
            this.alignmentIndicators = [];
        },

        /**
         * Initialize custom resources functionality
         */
        initCustomResources: function() {
            var self = this;
            
            // Check if custom resources section exists
            if (!$('#custom-resources-table').length) {
                return;
            }
            
            // Load custom resources table on init
            this.loadCustomResourcesTable();
            
            // File upload handler
            $('#resource-upload').on('change', function(e) {
                self.handleFileSelection(e);
            });
            
            // PNG type selection handler
            $('input[name="png-type"]').on('change', function() {
                self.handlePngTypeSelection();
            });
            
            // Upload button handlers
            $('#upload-font-btn').on('click', function() {
                self.uploadResource('font');
            });
            
            $('#upload-template-btn').on('click', function() {
                self.uploadResource('template');
            });
            
            $('#upload-token-btn').on('click', function() {
                self.uploadResource('token');
            });
        },

        /**
         * Load and display custom resources table
         */
        loadCustomResourcesTable: function() {
            var self = this;
            
            $.ajax({
                url: univoucher_image_templates_ajax.ajax_url,
                type: 'POST',
                data: {
                    action: 'univoucher_get_custom_resources',
                    nonce: univoucher_image_templates_ajax.nonce
                },
                success: function(response) {
                    if (response.success) {
                        self.renderCustomResourcesTable(response.data);
                    }
                },
                error: function() {
                    console.error('Failed to load custom resources');
                }
            });
        },

        /**
         * Render custom resources table
         */
        renderCustomResourcesTable: function(resources) {
            var self = this;
            var tbody = $('#custom-resources-tbody');
            tbody.empty();
            
            if (resources.length === 0) {
                tbody.append('<tr><td colspan="3" style="text-align: center; padding: 10px; color: #666; font-style: italic;">No custom resources found</td></tr>');
                return;
            }
            
            resources.forEach(function(resource) {
                var row = $('<tr>');
                row.append('<td style="padding: 6px; border: 1px solid #ddd;">' + resource.filename + '</td>');
                row.append('<td style="padding: 6px; border: 1px solid #ddd;">' + resource.type + '</td>');
                row.append('<td style="padding: 6px; border: 1px solid #ddd; text-align: center;"><button type="button" class="delete-resource" data-filename="' + resource.filename + '" data-type="' + resource.type + '" style="color: #d63638; border: none; background: none; cursor: pointer; font-size: 14px;">×</button></td>');
                tbody.append(row);
            });
            
            // Bind delete handlers
            $('.delete-resource').on('click', function() {
                var filename = $(this).data('filename');
                var type = $(this).data('type');
                
                if (confirm('Are you sure you want to delete "' + filename + '"?')) {
                    self.deleteResource(filename, type);
                }
            });
        },

        /**
         * Handle file selection
         */
        handleFileSelection: function(e) {
            var file = e.target.files[0];
            if (!file) {
                $('#upload-details').hide();
                return;
            }
            
            var fileExt = file.name.split('.').pop().toLowerCase();
            
            // Reset sections
            $('#font-upload-section, #png-upload-section, #template-upload-section, #token-upload-section').hide();
            
            if (fileExt === 'ttf') {
                // Font file
                $('#font-name').text(file.name);
                $('#font-upload-section').show();
                $('#upload-details').show();
            } else if (fileExt === 'png') {
                // PNG file - show type selection
                $('#png-upload-section').show();
                $('#upload-details').show();
                $('input[name="png-type"]').prop('checked', false);
            } else {
                alert('Invalid file type. Only PNG and TTF files are allowed.');
                e.target.value = '';
                $('#upload-details').hide();
            }
        },

        /**
         * Handle PNG type selection
         */
        handlePngTypeSelection: function() {
            var selectedType = $('input[name="png-type"]:checked').val();
            var file = $('#resource-upload')[0].files[0];
            
            // Hide both template and token sections first
            $('#template-upload-section, #token-upload-section').hide();
            
            if (selectedType === 'template' && file) {
                $('#template-name').text(file.name);
                $('#template-upload-section').show();
            } else if (selectedType === 'token') {
                $('#token-upload-section').show();
                $('#token-symbol').focus();
            }
        },

        /**
         * Upload resource file
         */
        uploadResource: function(type) {
            var self = this;
            var fileInput = $('#resource-upload')[0];
            var file = fileInput.files[0];
            
            if (!file) {
                alert('Please select a file first.');
                return;
            }
            
            var formData = new FormData();
            formData.append('file', file);
            formData.append('action', 'univoucher_upload_resource');
            formData.append('nonce', univoucher_image_templates_ajax.nonce);
            formData.append('upload_type', type);
            
            if (type === 'token') {
                var tokenSymbol = $('#token-symbol').val().trim();
                if (!tokenSymbol) {
                    alert('Please enter a token symbol.');
                    return;
                }
                formData.append('token_symbol', tokenSymbol);
            }
            
            // Show loading state
            var uploadButton = type === 'font' ? '#upload-font-btn' : 
                             type === 'template' ? '#upload-template-btn' : '#upload-token-btn';
            var originalText = $(uploadButton).text();
            $(uploadButton).prop('disabled', true).text('Uploading...');
            
            $.ajax({
                url: univoucher_image_templates_ajax.ajax_url,
                type: 'POST',
                data: formData,
                processData: false,
                contentType: false,
                success: function(response) {
                    if (response.success) {
                        alert('File uploaded successfully!');
                        
                        // Reset form
                        fileInput.value = '';
                        $('#upload-details').hide();
                        $('#token-symbol').val('');
                        
                        // Reload table
                        self.loadCustomResourcesTable();
                        
                        // Refresh templates/fonts dropdown if applicable
                        if (type === 'template') {
                            location.reload(); // Simple reload to update template dropdown
                        }
                    } else {
                        alert('Upload failed: ' + (response.data.message || 'Unknown error'));
                    }
                },
                error: function() {
                    alert('Upload failed. Please try again.');
                },
                complete: function() {
                    // Reset button state
                    $(uploadButton).prop('disabled', false).text(originalText);
                }
            });
        },



        /**
         * Add reset button for default templates
         */
        addResetButton: function() {
            var self = this;
            
            // Check if we're on a default template
            var currentTemplate = $('#univoucher_wc_image_template').val();
            this.showResetButtonForTemplate(currentTemplate);
            
            // Monitor template changes to show/hide reset button
            $('#univoucher_wc_image_template').on('change', function() {
                var selectedTemplate = $(this).val();
                self.showResetButtonForTemplate(selectedTemplate);
            });
        },

        /**
         * Show reset button for specific template
         */
        showResetButtonForTemplate: function(templateName) {
            var self = this;
            var $resetButton = $('#reset-template-btn');
            $resetButton.remove(); // Remove existing button
            
            var buttonText = '';
            var resetFunction = null;
            
            if (templateName === 'UniVoucher-wide-4x3.png') {
                buttonText = 'Reset to Wide 4x3 Defaults';
                resetFunction = function() {
                    if (confirm('Reset all settings to Wide 4x3 template defaults?')) {
                        self.resetToWide4x3Defaults();
                    }
                };
            } else if (templateName === 'UniVoucher-square.png') {
                buttonText = 'Reset to Square Defaults';
                resetFunction = function() {
                    if (confirm('Reset all settings to Square template defaults?')) {
                        self.resetToSquareDefaults();
                    }
                };
            } else if (templateName === 'UniVoucher-wide-croped.png') {
                buttonText = 'Reset to Wide Cropped Defaults';
                resetFunction = function() {
                    if (confirm('Reset all settings to Wide Cropped template defaults?')) {
                        self.resetToWideCroppedDefaults();
                    }
                };
            }
            
            if (buttonText && resetFunction) {
                var $templateSelect = $('#univoucher_wc_image_template');
                if ($templateSelect.length) {
                    var $newResetButton = $('<button type="button" id="reset-template-btn" class="button button-secondary" style="margin-top: 10px;">' + buttonText + '</button>');
                    $templateSelect.after($newResetButton);
                    
                    $newResetButton.on('click', resetFunction);
                }
            }
        },



        /**
         * Delete resource file
         */
        deleteResource: function(filename, type) {
            var self = this;
            
            $.ajax({
                url: univoucher_image_templates_ajax.ajax_url,
                type: 'POST',
                data: {
                    action: 'univoucher_delete_resource',
                    nonce: univoucher_image_templates_ajax.nonce,
                    filename: filename,
                    file_type: type
                },
                success: function(response) {
                    if (response.success) {
                        alert('File deleted successfully!');
                        self.loadCustomResourcesTable();
                        
                        // Refresh page if template was deleted to update dropdown
                        if (type === 'template') {
                            location.reload();
                        }
                    } else {
                        alert('Delete failed: ' + (response.data.message || 'Unknown error'));
                    }
                },
                error: function() {
                    alert('Delete failed. Please try again.');
                }
            });
        }
    };

    // Initialize when DOM is ready
    UniVoucherImageTemplates.init();
}); 