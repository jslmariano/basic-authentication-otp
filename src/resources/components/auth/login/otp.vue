<template>
    <v-dialog
      v-model="show"
      width="800"
      :disabled="disabled"
      fullscreen
      hide-overlay
      transition="dialog-bottom-transition"
    >
    <v-overlay :opacity="0.8">
      <v-container container--fluid fill-height>
        <v-layout align-center justify-center>
          <v-flex xs12 s12 md6 lg6 xl2>
            <v-card class="elevation-12">
              <v-card-text>
                <v-layout row wrap>

                  <v-form @submit.prevent>
                    <v-container fluid>
                      <v-row align="center">
                        <v-col cols="12">
                          <v-alert
                            color="error"
                            dark
                            :value="error != ''"
                            icon="warning"
                            transition="fade-transition"
                            >{{ error }}</v-alert
                          >
                        </v-col>
                        <v-col cols="12">
                          <div class="otp-input-wrapper">
                            <v-otp-input
                              ref="otpInput"
                              input-classes="otp-input"
                              separator="-"
                              :num-inputs="otp_code_length"
                              :should-auto-focus="true"
                              :is-input-num="true"
                              @on-change="handleOnChange"
                              @on-complete="handleOnComplete"
                            />
                          </div>
                        </v-col>
                        <v-col cols="12">
                          <v-layout align-center class="mt-4">
                            <v-flex xs12 text-center>
                              <p>
                                Don't have the code?
                              </p>
                            </v-flex>
                          </v-layout>
                        </v-col>
                        <v-col cols="12">
                          <v-layout align-center class="mt-4">
                            <v-flex xs12 text-center>
                              <v-btn
                                :loading="resend_loading || verify_loading"
                                :disabled="resend_loading || verify_loading"
                                color="primary"
                                type="submit"
                                @click="resend()"
                                name="resend-button"
                                >Resend OTP</v-btn
                              >
                            </v-flex>
                          </v-layout>
                        </v-col>
                      </v-row>
                    </v-container>
                  </v-form>
                </v-layout>
              </v-card-text>
            </v-card>
          </v-flex>
        </v-layout>
      </v-container>
    </v-overlay>

  </v-dialog>
</template>

<style>
  .otp-input-wrapper {
    display: flex;
    flex-direction: row;
    justify-content: center;
  }
  .otp-input {
    width: 40px;
    height: 40px;
    padding: 5px;
    margin: 0 10px;
    font-size: 20px;
    border-radius: 4px;
    border: 1px solid rgba(0, 0, 0, 0.3);
    text-align: center;
    &.error {
      border: 1px solid red !important;
    }
  }
  .otp-input::-webkit-inner-spin-button,
  .otp-input::-webkit-outer-spin-button {
    -webkit-appearance: none;
    margin: 0;
  }
</style>

<script>
import CONSTANTS from '../../../constants';
import OtpInput from "@bachdgvn/vue-otp-input";

export default {
    name: 'AuthLoginOtp',

    components: {
      'v-otp-input': OtpInput,
    },

    props: {
        password: {
            default: false,
            type: String
        },
        disabled: {
            default: false,
            type: Boolean
        },
        email: {
            default: null,
            type: String
        },
        otp_code: {
            default: null,
            type: String
        },
        otp_code_length: {
            default: 4,
            type: Number
        }
    },

    computed: {
    },

    watch: {
    },

    data() {
        return {
            error: '',
            verify_loading: false,
            resend_loading: false,
            show: false,
          };
    },

    mounted() {
      this.initOtpLength();
      console.log(this.$refs);
      console.log('aut-login-otp:mounted');
    },

    methods: {
        /**
         * Initializes the otp length.
         */
        initOtpLength() {
          this.$http
            .get('/auth/otp/length')
            .then(res => {
                this.otp_code_length = parseInt(res.data.otp_length);
            })
            .catch(err => {
                console.warn(err);
                this.otp_code_length = 4;
            });
        },

        triggerShow() {
          this.error = '';
          this.show = true;
        },

        handleOnComplete(value) {
          console.log('OTP completed: ', value);
          this.otp_code = value;
          this.verify();
        },
        handleOnChange(value) {
          console.log('OTP changed: ', value);
        },
        handleClearInput() {
          this.$refs.otpInput.clearInput();
        },

        /**
         * Cancel Edit just hide the popup component
         */
        cancelEdit() {
            this.show = false;
        },

        /**
         * Check the credential and if the user has OTP  enabled
         */
        check() {
          this.verify_loading = true;
          this.$validator.validate().then(result => {
            this.$http
              .post('/auth/otp/verify', {
                password: this.password,
                email:    this.email,
              })
              .then(res => {
                if (res.data.otp_status == 'ready') {
                  this.triggerShow();
                  return true;
                }
                if (['valid_otp','otp_disabled','already_verified'].includes(res.data.otp_status)) {
                  this.emitVerified(res);
                  return true;
                }
                this.emitCheckError(res);
              })
              .catch(res => {
                console.warn(res);
                if (res.response && res.response.status === 429) {
                  try {
                    this.error = res.response.data.errors.email[0];
                  } catch (err) {
                    this.error = 'Too many login attempts';
                  }
                }
                this.emitCheckError(res);
              })
              .finally(e => {
                  this.verify_loading = false;
              });
            });
        },

        /**
         * Verify OTP code
         */
        verify() {
          this.verify_loading = true;
          this.$http
            .post('/auth/otp/verify', {
                password: this.password,
                email:    this.email,
                code:     this.otp_code,
            })
            .then(res => {
                if (['valid_otp','otp_disabled','already_verified'].includes(res.data.otp_status)) {
                  this.emitVerified(res);
                  return true;
                }
                this.emitVerifyError(res);
            })
            .catch(err => {
              console.warn(err);
              this.emitVerifyError(err);
            })
            .finally(e => {
              this.verify_loading = false;
            });
        },

        /**
         * Re-send the OTP Code
         */
        resend() {
          this.resend_loading = true;
          this.$http
            .get('/auth/otp/refresh' + "?email=" + this.email)
            .then(res => {
                if (res.data.status == 'success') {
                    this.emitResend(res);
                    return true;
                }
                this.emitResendError(res);
            })
            .catch(err => {
                console.warn(err);
                this.emitResendError(err);
            })
            .finally(e => {
                this.resend_loading = false;
                this.handleClearInput();
            });
        },

        /**
         * Emit verified to the parent vue layotu
         *
         * @param      {object}  res     The resource
         */
        emitVerified(res) {
            if (res.data.otp_status == 'valid_otp') {
                // emit item
                swal({
                    title: 'OTP Code',
                    text: 'OTP succesfully verified',
                    icon: 'success'
                });
            }
            this.$emit('on-verified', res);
            this.show = false;
        },

        /**
         * Emit Resend to the parent vue layotu
         *
         * @param      {object}  res     The resource
         */
        emitResend(res) {
            // emit item
            this.handleClearInput();
            swal({
                title: 'OTP Code',
                text: res.data.msg,
                icon: 'success'
            });
            this.$emit('on-resend', res);
            this.show = false;
        },

        /**
         * Emit check error to the parent vue layotu
         *
         * @param      {object}  res     The resource
         */
        emitCheckError(res) {
            // emit item
            this.handleClearInput();
            swal({
                title: 'OTP Code',
                text: res.data.msg,
                icon: 'error'
            });
            this.$emit('on-check-error', res);
            this.show = false;
        },

        /**
         * Emit resend error to the parent vue layotu
         *
         * @param      {object}  res     The resource
         */
        emitResendError(res) {
            // emit item
            this.handleClearInput();
            swal({
                title: 'OTP Code',
                text: res.data.msg,
                icon: 'error'
            });
            this.$emit('on-resend-error', res);
            this.show = false;
        },

        /**
         * Emit Verify error to the parent vue layotu
         *
         * @param      {object}  res     The resource
         */
        emitVerifyError(res) {
            // emit item
            this.handleClearInput();
            swal({
                title: 'OTP Code',
                text: res.data.msg,
                icon: 'error'
            });
            this.$emit('on-verify-error', res);
            this.show = false;
        },

        /**
         * END METHOD
         */
    }
    /**
     * END DEFAULT
     */
};

</script>
