export type Json =
  | string
  | number
  | boolean
  | null
  | { [key: string]: Json | undefined }
  | Json[]

export type Database = {
  // Allows to automatically instantiate createClient with right options
  // instead of createClient<Database, { PostgrestVersion: 'XX' }>(URL, KEY)
  __InternalSupabase: {
    PostgrestVersion: "14.18"
  }
  public: {
    Tables: {
      alerts: {
        Row: {
          alert_key: string
          audience: string
          client_id: string | null
          created_at: string
          cycle_id: string | null
          dedupe_key: string
          deliverable_id: string | null
          error: string | null
          handled_at: string | null
          handled_by: string | null
          id: number
          payload: Json
          priority: string
          recipient_staff_id: string | null
          resolution: string | null
          sent_at: string | null
          session_id: string | null
          status: string
        }
        Insert: {
          alert_key: string
          audience: string
          client_id?: string | null
          created_at?: string
          cycle_id?: string | null
          dedupe_key: string
          deliverable_id?: string | null
          error?: string | null
          handled_at?: string | null
          handled_by?: string | null
          id?: number
          payload?: Json
          priority?: string
          recipient_staff_id?: string | null
          resolution?: string | null
          sent_at?: string | null
          session_id?: string | null
          status?: string
        }
        Update: {
          alert_key?: string
          audience?: string
          client_id?: string | null
          created_at?: string
          cycle_id?: string | null
          dedupe_key?: string
          deliverable_id?: string | null
          error?: string | null
          handled_at?: string | null
          handled_by?: string | null
          id?: number
          payload?: Json
          priority?: string
          recipient_staff_id?: string | null
          resolution?: string | null
          sent_at?: string | null
          session_id?: string | null
          status?: string
        }
        Relationships: [
          {
            foreignKeyName: "alerts_client_id_fkey"
            columns: ["client_id"]
            isOneToOne: false
            referencedRelation: "clients"
            referencedColumns: ["id"]
          },
          {
            foreignKeyName: "alerts_client_id_fkey"
            columns: ["client_id"]
            isOneToOne: false
            referencedRelation: "v_client_overview"
            referencedColumns: ["client_id"]
          },
          {
            foreignKeyName: "alerts_cycle_id_fkey"
            columns: ["cycle_id"]
            isOneToOne: false
            referencedRelation: "cycles"
            referencedColumns: ["id"]
          },
          {
            foreignKeyName: "alerts_cycle_id_fkey"
            columns: ["cycle_id"]
            isOneToOne: false
            referencedRelation: "v_client_overview"
            referencedColumns: ["cycle_id"]
          },
          {
            foreignKeyName: "alerts_cycle_id_fkey"
            columns: ["cycle_id"]
            isOneToOne: false
            referencedRelation: "v_cycle_balance"
            referencedColumns: ["cycle_id"]
          },
          {
            foreignKeyName: "alerts_deliverable_id_fkey"
            columns: ["deliverable_id"]
            isOneToOne: false
            referencedRelation: "deliverables"
            referencedColumns: ["id"]
          },
          {
            foreignKeyName: "alerts_deliverable_id_fkey"
            columns: ["deliverable_id"]
            isOneToOne: false
            referencedRelation: "v_deliverable_credits"
            referencedColumns: ["deliverable_id"]
          },
          {
            foreignKeyName: "alerts_handled_by_fkey"
            columns: ["handled_by"]
            isOneToOne: false
            referencedRelation: "staff"
            referencedColumns: ["id"]
          },
          {
            foreignKeyName: "alerts_handled_by_fkey"
            columns: ["handled_by"]
            isOneToOne: false
            referencedRelation: "v_challenge_progress"
            referencedColumns: ["staff_id"]
          },
          {
            foreignKeyName: "alerts_handled_by_fkey"
            columns: ["handled_by"]
            isOneToOne: false
            referencedRelation: "v_feedback_by_staff"
            referencedColumns: ["staff_id"]
          },
          {
            foreignKeyName: "alerts_handled_by_fkey"
            columns: ["handled_by"]
            isOneToOne: false
            referencedRelation: "v_leaderboard"
            referencedColumns: ["staff_id"]
          },
          {
            foreignKeyName: "alerts_recipient_staff_id_fkey"
            columns: ["recipient_staff_id"]
            isOneToOne: false
            referencedRelation: "staff"
            referencedColumns: ["id"]
          },
          {
            foreignKeyName: "alerts_recipient_staff_id_fkey"
            columns: ["recipient_staff_id"]
            isOneToOne: false
            referencedRelation: "v_challenge_progress"
            referencedColumns: ["staff_id"]
          },
          {
            foreignKeyName: "alerts_recipient_staff_id_fkey"
            columns: ["recipient_staff_id"]
            isOneToOne: false
            referencedRelation: "v_feedback_by_staff"
            referencedColumns: ["staff_id"]
          },
          {
            foreignKeyName: "alerts_recipient_staff_id_fkey"
            columns: ["recipient_staff_id"]
            isOneToOne: false
            referencedRelation: "v_leaderboard"
            referencedColumns: ["staff_id"]
          },
          {
            foreignKeyName: "alerts_session_id_fkey"
            columns: ["session_id"]
            isOneToOne: false
            referencedRelation: "studio_sessions"
            referencedColumns: ["id"]
          },
          {
            foreignKeyName: "alerts_session_id_fkey"
            columns: ["session_id"]
            isOneToOne: false
            referencedRelation: "v_today_sessions"
            referencedColumns: ["session_id"]
          },
        ]
      }
      bonus_payouts: {
        Row: {
          amount: number
          decided_at: string | null
          decided_by: string | null
          id: string
          month: string
          note: string | null
          paid_at: string | null
          staff_id: string
          status: string
          xp: number
        }
        Insert: {
          amount: number
          decided_at?: string | null
          decided_by?: string | null
          id?: string
          month: string
          note?: string | null
          paid_at?: string | null
          staff_id: string
          status?: string
          xp: number
        }
        Update: {
          amount?: number
          decided_at?: string | null
          decided_by?: string | null
          id?: string
          month?: string
          note?: string | null
          paid_at?: string | null
          staff_id?: string
          status?: string
          xp?: number
        }
        Relationships: [
          {
            foreignKeyName: "bonus_payouts_decided_by_fkey"
            columns: ["decided_by"]
            isOneToOne: false
            referencedRelation: "staff"
            referencedColumns: ["id"]
          },
          {
            foreignKeyName: "bonus_payouts_decided_by_fkey"
            columns: ["decided_by"]
            isOneToOne: false
            referencedRelation: "v_challenge_progress"
            referencedColumns: ["staff_id"]
          },
          {
            foreignKeyName: "bonus_payouts_decided_by_fkey"
            columns: ["decided_by"]
            isOneToOne: false
            referencedRelation: "v_feedback_by_staff"
            referencedColumns: ["staff_id"]
          },
          {
            foreignKeyName: "bonus_payouts_decided_by_fkey"
            columns: ["decided_by"]
            isOneToOne: false
            referencedRelation: "v_leaderboard"
            referencedColumns: ["staff_id"]
          },
          {
            foreignKeyName: "bonus_payouts_staff_id_fkey"
            columns: ["staff_id"]
            isOneToOne: false
            referencedRelation: "staff"
            referencedColumns: ["id"]
          },
          {
            foreignKeyName: "bonus_payouts_staff_id_fkey"
            columns: ["staff_id"]
            isOneToOne: false
            referencedRelation: "v_challenge_progress"
            referencedColumns: ["staff_id"]
          },
          {
            foreignKeyName: "bonus_payouts_staff_id_fkey"
            columns: ["staff_id"]
            isOneToOne: false
            referencedRelation: "v_feedback_by_staff"
            referencedColumns: ["staff_id"]
          },
          {
            foreignKeyName: "bonus_payouts_staff_id_fkey"
            columns: ["staff_id"]
            isOneToOne: false
            referencedRelation: "v_leaderboard"
            referencedColumns: ["staff_id"]
          },
        ]
      }
      challenge_completions: {
        Row: {
          challenge_id: string
          completed_at: string
          staff_id: string
        }
        Insert: {
          challenge_id: string
          completed_at?: string
          staff_id: string
        }
        Update: {
          challenge_id?: string
          completed_at?: string
          staff_id?: string
        }
        Relationships: [
          {
            foreignKeyName: "challenge_completions_challenge_id_fkey"
            columns: ["challenge_id"]
            isOneToOne: false
            referencedRelation: "challenges"
            referencedColumns: ["id"]
          },
          {
            foreignKeyName: "challenge_completions_challenge_id_fkey"
            columns: ["challenge_id"]
            isOneToOne: false
            referencedRelation: "v_challenge_progress"
            referencedColumns: ["challenge_id"]
          },
          {
            foreignKeyName: "challenge_completions_staff_id_fkey"
            columns: ["staff_id"]
            isOneToOne: false
            referencedRelation: "staff"
            referencedColumns: ["id"]
          },
          {
            foreignKeyName: "challenge_completions_staff_id_fkey"
            columns: ["staff_id"]
            isOneToOne: false
            referencedRelation: "v_challenge_progress"
            referencedColumns: ["staff_id"]
          },
          {
            foreignKeyName: "challenge_completions_staff_id_fkey"
            columns: ["staff_id"]
            isOneToOne: false
            referencedRelation: "v_feedback_by_staff"
            referencedColumns: ["staff_id"]
          },
          {
            foreignKeyName: "challenge_completions_staff_id_fkey"
            columns: ["staff_id"]
            isOneToOne: false
            referencedRelation: "v_leaderboard"
            referencedColumns: ["staff_id"]
          },
        ]
      }
      challenges: {
        Row: {
          active: boolean
          created_at: string
          description_ar: string | null
          ends_on: string
          id: string
          metric: string
          name_ar: string
          reward_xp: number
          roles: string[] | null
          starts_on: string
          target: number
        }
        Insert: {
          active?: boolean
          created_at?: string
          description_ar?: string | null
          ends_on?: string
          id?: string
          metric: string
          name_ar: string
          reward_xp?: number
          roles?: string[] | null
          starts_on?: string
          target: number
        }
        Update: {
          active?: boolean
          created_at?: string
          description_ar?: string | null
          ends_on?: string
          id?: string
          metric?: string
          name_ar?: string
          reward_xp?: number
          roles?: string[] | null
          starts_on?: string
          target?: number
        }
        Relationships: []
      }
      checklist_runs: {
        Row: {
          client_confirmed_at: string | null
          client_id: string | null
          deliverable_id: string | null
          done: boolean
          done_at: string
          done_by: string | null
          id: string
          item_id: string
          note: string | null
          session_id: string | null
        }
        Insert: {
          client_confirmed_at?: string | null
          client_id?: string | null
          deliverable_id?: string | null
          done?: boolean
          done_at?: string
          done_by?: string | null
          id?: string
          item_id: string
          note?: string | null
          session_id?: string | null
        }
        Update: {
          client_confirmed_at?: string | null
          client_id?: string | null
          deliverable_id?: string | null
          done?: boolean
          done_at?: string
          done_by?: string | null
          id?: string
          item_id?: string
          note?: string | null
          session_id?: string | null
        }
        Relationships: [
          {
            foreignKeyName: "checklist_runs_client_id_fkey"
            columns: ["client_id"]
            isOneToOne: false
            referencedRelation: "clients"
            referencedColumns: ["id"]
          },
          {
            foreignKeyName: "checklist_runs_client_id_fkey"
            columns: ["client_id"]
            isOneToOne: false
            referencedRelation: "v_client_overview"
            referencedColumns: ["client_id"]
          },
          {
            foreignKeyName: "checklist_runs_deliverable_id_fkey"
            columns: ["deliverable_id"]
            isOneToOne: false
            referencedRelation: "deliverables"
            referencedColumns: ["id"]
          },
          {
            foreignKeyName: "checklist_runs_deliverable_id_fkey"
            columns: ["deliverable_id"]
            isOneToOne: false
            referencedRelation: "v_deliverable_credits"
            referencedColumns: ["deliverable_id"]
          },
          {
            foreignKeyName: "checklist_runs_done_by_fkey"
            columns: ["done_by"]
            isOneToOne: false
            referencedRelation: "staff"
            referencedColumns: ["id"]
          },
          {
            foreignKeyName: "checklist_runs_done_by_fkey"
            columns: ["done_by"]
            isOneToOne: false
            referencedRelation: "v_challenge_progress"
            referencedColumns: ["staff_id"]
          },
          {
            foreignKeyName: "checklist_runs_done_by_fkey"
            columns: ["done_by"]
            isOneToOne: false
            referencedRelation: "v_feedback_by_staff"
            referencedColumns: ["staff_id"]
          },
          {
            foreignKeyName: "checklist_runs_done_by_fkey"
            columns: ["done_by"]
            isOneToOne: false
            referencedRelation: "v_leaderboard"
            referencedColumns: ["staff_id"]
          },
          {
            foreignKeyName: "checklist_runs_item_id_fkey"
            columns: ["item_id"]
            isOneToOne: false
            referencedRelation: "checklist_template_items"
            referencedColumns: ["id"]
          },
          {
            foreignKeyName: "checklist_runs_session_id_fkey"
            columns: ["session_id"]
            isOneToOne: false
            referencedRelation: "studio_sessions"
            referencedColumns: ["id"]
          },
          {
            foreignKeyName: "checklist_runs_session_id_fkey"
            columns: ["session_id"]
            isOneToOne: false
            referencedRelation: "v_today_sessions"
            referencedColumns: ["session_id"]
          },
        ]
      }
      checklist_template_items: {
        Row: {
          action: string | null
          client_facing: boolean
          id: string
          is_required: boolean
          role: string | null
          sort: number
          template_id: string
          text_ar: string
        }
        Insert: {
          action?: string | null
          client_facing?: boolean
          id?: string
          is_required?: boolean
          role?: string | null
          sort?: number
          template_id: string
          text_ar: string
        }
        Update: {
          action?: string | null
          client_facing?: boolean
          id?: string
          is_required?: boolean
          role?: string | null
          sort?: number
          template_id?: string
          text_ar?: string
        }
        Relationships: [
          {
            foreignKeyName: "checklist_template_items_template_id_fkey"
            columns: ["template_id"]
            isOneToOne: false
            referencedRelation: "checklist_templates"
            referencedColumns: ["id"]
          },
        ]
      }
      checklist_templates: {
        Row: {
          active: boolean
          applies_to: string[] | null
          id: string
          name: string
          stage: string
        }
        Insert: {
          active?: boolean
          applies_to?: string[] | null
          id?: string
          name: string
          stage: string
        }
        Update: {
          active?: boolean
          applies_to?: string[] | null
          id?: string
          name?: string
          stage?: string
        }
        Relationships: []
      }
      clients: {
        Row: {
          account_manager_id: string | null
          acquired_at: string
          approval_sla_hours: number
          brand_assets_url: string | null
          buffer_weeks: number
          business_name: string | null
          caption_tone: string | null
          churn_reason: string | null
          churned_at: string | null
          code: string | null
          contact_person: string | null
          contract_path: string | null
          created_at: string
          id: string
          industry: string | null
          name: string
          needs_approval: boolean
          notes: string | null
          perfex_client_id: number | null
          phone_wa: string
          platforms: string[]
          preferred_shoot_day: number | null
          reels_per_session_avg: number | null
          shoot_frequency: string | null
          status: string
          wa_group_jid: string | null
          wa_group_link: string | null
        }
        Insert: {
          account_manager_id?: string | null
          acquired_at?: string
          approval_sla_hours?: number
          brand_assets_url?: string | null
          buffer_weeks?: number
          business_name?: string | null
          caption_tone?: string | null
          churn_reason?: string | null
          churned_at?: string | null
          code?: string | null
          contact_person?: string | null
          contract_path?: string | null
          created_at?: string
          id?: string
          industry?: string | null
          name: string
          needs_approval?: boolean
          notes?: string | null
          perfex_client_id?: number | null
          phone_wa: string
          platforms?: string[]
          preferred_shoot_day?: number | null
          reels_per_session_avg?: number | null
          shoot_frequency?: string | null
          status?: string
          wa_group_jid?: string | null
          wa_group_link?: string | null
        }
        Update: {
          account_manager_id?: string | null
          acquired_at?: string
          approval_sla_hours?: number
          brand_assets_url?: string | null
          buffer_weeks?: number
          business_name?: string | null
          caption_tone?: string | null
          churn_reason?: string | null
          churned_at?: string | null
          code?: string | null
          contact_person?: string | null
          contract_path?: string | null
          created_at?: string
          id?: string
          industry?: string | null
          name?: string
          needs_approval?: boolean
          notes?: string | null
          perfex_client_id?: number | null
          phone_wa?: string
          platforms?: string[]
          preferred_shoot_day?: number | null
          reels_per_session_avg?: number | null
          shoot_frequency?: string | null
          status?: string
          wa_group_jid?: string | null
          wa_group_link?: string | null
        }
        Relationships: [
          {
            foreignKeyName: "clients_account_manager_id_fkey"
            columns: ["account_manager_id"]
            isOneToOne: false
            referencedRelation: "staff"
            referencedColumns: ["id"]
          },
          {
            foreignKeyName: "clients_account_manager_id_fkey"
            columns: ["account_manager_id"]
            isOneToOne: false
            referencedRelation: "v_challenge_progress"
            referencedColumns: ["staff_id"]
          },
          {
            foreignKeyName: "clients_account_manager_id_fkey"
            columns: ["account_manager_id"]
            isOneToOne: false
            referencedRelation: "v_feedback_by_staff"
            referencedColumns: ["staff_id"]
          },
          {
            foreignKeyName: "clients_account_manager_id_fkey"
            columns: ["account_manager_id"]
            isOneToOne: false
            referencedRelation: "v_leaderboard"
            referencedColumns: ["staff_id"]
          },
        ]
      }
      consumption_log: {
        Row: {
          created_at: string
          created_by: string | null
          cycle_id: string
          deliverable_id: string | null
          deliverable_type: string
          id: number
          note: string | null
          qty: number
          reason: string
          session_id: string | null
        }
        Insert: {
          created_at?: string
          created_by?: string | null
          cycle_id: string
          deliverable_id?: string | null
          deliverable_type: string
          id?: number
          note?: string | null
          qty: number
          reason: string
          session_id?: string | null
        }
        Update: {
          created_at?: string
          created_by?: string | null
          cycle_id?: string
          deliverable_id?: string | null
          deliverable_type?: string
          id?: number
          note?: string | null
          qty?: number
          reason?: string
          session_id?: string | null
        }
        Relationships: [
          {
            foreignKeyName: "consumption_log_created_by_fkey"
            columns: ["created_by"]
            isOneToOne: false
            referencedRelation: "staff"
            referencedColumns: ["id"]
          },
          {
            foreignKeyName: "consumption_log_created_by_fkey"
            columns: ["created_by"]
            isOneToOne: false
            referencedRelation: "v_challenge_progress"
            referencedColumns: ["staff_id"]
          },
          {
            foreignKeyName: "consumption_log_created_by_fkey"
            columns: ["created_by"]
            isOneToOne: false
            referencedRelation: "v_feedback_by_staff"
            referencedColumns: ["staff_id"]
          },
          {
            foreignKeyName: "consumption_log_created_by_fkey"
            columns: ["created_by"]
            isOneToOne: false
            referencedRelation: "v_leaderboard"
            referencedColumns: ["staff_id"]
          },
          {
            foreignKeyName: "consumption_log_cycle_id_fkey"
            columns: ["cycle_id"]
            isOneToOne: false
            referencedRelation: "cycles"
            referencedColumns: ["id"]
          },
          {
            foreignKeyName: "consumption_log_cycle_id_fkey"
            columns: ["cycle_id"]
            isOneToOne: false
            referencedRelation: "v_client_overview"
            referencedColumns: ["cycle_id"]
          },
          {
            foreignKeyName: "consumption_log_cycle_id_fkey"
            columns: ["cycle_id"]
            isOneToOne: false
            referencedRelation: "v_cycle_balance"
            referencedColumns: ["cycle_id"]
          },
          {
            foreignKeyName: "consumption_log_deliverable_id_fkey"
            columns: ["deliverable_id"]
            isOneToOne: false
            referencedRelation: "deliverables"
            referencedColumns: ["id"]
          },
          {
            foreignKeyName: "consumption_log_deliverable_id_fkey"
            columns: ["deliverable_id"]
            isOneToOne: false
            referencedRelation: "v_deliverable_credits"
            referencedColumns: ["deliverable_id"]
          },
          {
            foreignKeyName: "consumption_log_deliverable_type_fkey"
            columns: ["deliverable_type"]
            isOneToOne: false
            referencedRelation: "deliverable_types"
            referencedColumns: ["code"]
          },
          {
            foreignKeyName: "consumption_log_session_id_fkey"
            columns: ["session_id"]
            isOneToOne: false
            referencedRelation: "studio_sessions"
            referencedColumns: ["id"]
          },
          {
            foreignKeyName: "consumption_log_session_id_fkey"
            columns: ["session_id"]
            isOneToOne: false
            referencedRelation: "v_today_sessions"
            referencedColumns: ["session_id"]
          },
        ]
      }
      content_slots: {
        Row: {
          client_id: string
          deliverable_id: string | null
          deliverable_type: string
          id: string
          publish_time: string | null
          slot_date: string
          status: string
        }
        Insert: {
          client_id: string
          deliverable_id?: string | null
          deliverable_type: string
          id?: string
          publish_time?: string | null
          slot_date: string
          status?: string
        }
        Update: {
          client_id?: string
          deliverable_id?: string | null
          deliverable_type?: string
          id?: string
          publish_time?: string | null
          slot_date?: string
          status?: string
        }
        Relationships: [
          {
            foreignKeyName: "content_slots_client_id_fkey"
            columns: ["client_id"]
            isOneToOne: false
            referencedRelation: "clients"
            referencedColumns: ["id"]
          },
          {
            foreignKeyName: "content_slots_client_id_fkey"
            columns: ["client_id"]
            isOneToOne: false
            referencedRelation: "v_client_overview"
            referencedColumns: ["client_id"]
          },
          {
            foreignKeyName: "content_slots_deliverable_id_fkey"
            columns: ["deliverable_id"]
            isOneToOne: false
            referencedRelation: "deliverables"
            referencedColumns: ["id"]
          },
          {
            foreignKeyName: "content_slots_deliverable_id_fkey"
            columns: ["deliverable_id"]
            isOneToOne: false
            referencedRelation: "v_deliverable_credits"
            referencedColumns: ["deliverable_id"]
          },
          {
            foreignKeyName: "content_slots_deliverable_type_fkey"
            columns: ["deliverable_type"]
            isOneToOne: false
            referencedRelation: "deliverable_types"
            referencedColumns: ["code"]
          },
        ]
      }
      cycle_entitlements: {
        Row: {
          bonus: number
          cycle_id: string
          deliverable_type: string
          forfeited: number
          granted: number
          used: number
        }
        Insert: {
          bonus?: number
          cycle_id: string
          deliverable_type: string
          forfeited?: number
          granted?: number
          used?: number
        }
        Update: {
          bonus?: number
          cycle_id?: string
          deliverable_type?: string
          forfeited?: number
          granted?: number
          used?: number
        }
        Relationships: [
          {
            foreignKeyName: "cycle_entitlements_cycle_id_fkey"
            columns: ["cycle_id"]
            isOneToOne: false
            referencedRelation: "cycles"
            referencedColumns: ["id"]
          },
          {
            foreignKeyName: "cycle_entitlements_cycle_id_fkey"
            columns: ["cycle_id"]
            isOneToOne: false
            referencedRelation: "v_client_overview"
            referencedColumns: ["cycle_id"]
          },
          {
            foreignKeyName: "cycle_entitlements_cycle_id_fkey"
            columns: ["cycle_id"]
            isOneToOne: false
            referencedRelation: "v_cycle_balance"
            referencedColumns: ["cycle_id"]
          },
          {
            foreignKeyName: "cycle_entitlements_deliverable_type_fkey"
            columns: ["deliverable_type"]
            isOneToOne: false
            referencedRelation: "deliverable_types"
            referencedColumns: ["code"]
          },
        ]
      }
      cycles: {
        Row: {
          activated_at: string | null
          client_id: string
          closed_at: string | null
          created_at: string
          cycle_no: number
          end_date: string
          id: string
          monthly_value: number | null
          price: number
          start_date: string
          status: string
          subscription_id: string
        }
        Insert: {
          activated_at?: string | null
          client_id: string
          closed_at?: string | null
          created_at?: string
          cycle_no: number
          end_date: string
          id?: string
          monthly_value?: number | null
          price: number
          start_date: string
          status?: string
          subscription_id: string
        }
        Update: {
          activated_at?: string | null
          client_id?: string
          closed_at?: string | null
          created_at?: string
          cycle_no?: number
          end_date?: string
          id?: string
          monthly_value?: number | null
          price?: number
          start_date?: string
          status?: string
          subscription_id?: string
        }
        Relationships: [
          {
            foreignKeyName: "cycles_client_id_fkey"
            columns: ["client_id"]
            isOneToOne: false
            referencedRelation: "clients"
            referencedColumns: ["id"]
          },
          {
            foreignKeyName: "cycles_client_id_fkey"
            columns: ["client_id"]
            isOneToOne: false
            referencedRelation: "v_client_overview"
            referencedColumns: ["client_id"]
          },
          {
            foreignKeyName: "cycles_subscription_id_fkey"
            columns: ["subscription_id"]
            isOneToOne: false
            referencedRelation: "subscriptions"
            referencedColumns: ["id"]
          },
        ]
      }
      deliverable_stage_history: {
        Row: {
          changed_at: string
          changed_by: string | null
          deliverable_id: string
          from_stage: string | null
          id: number
          to_stage: string
        }
        Insert: {
          changed_at?: string
          changed_by?: string | null
          deliverable_id: string
          from_stage?: string | null
          id?: number
          to_stage: string
        }
        Update: {
          changed_at?: string
          changed_by?: string | null
          deliverable_id?: string
          from_stage?: string | null
          id?: number
          to_stage?: string
        }
        Relationships: [
          {
            foreignKeyName: "deliverable_stage_history_changed_by_fkey"
            columns: ["changed_by"]
            isOneToOne: false
            referencedRelation: "staff"
            referencedColumns: ["id"]
          },
          {
            foreignKeyName: "deliverable_stage_history_changed_by_fkey"
            columns: ["changed_by"]
            isOneToOne: false
            referencedRelation: "v_challenge_progress"
            referencedColumns: ["staff_id"]
          },
          {
            foreignKeyName: "deliverable_stage_history_changed_by_fkey"
            columns: ["changed_by"]
            isOneToOne: false
            referencedRelation: "v_feedback_by_staff"
            referencedColumns: ["staff_id"]
          },
          {
            foreignKeyName: "deliverable_stage_history_changed_by_fkey"
            columns: ["changed_by"]
            isOneToOne: false
            referencedRelation: "v_leaderboard"
            referencedColumns: ["staff_id"]
          },
          {
            foreignKeyName: "deliverable_stage_history_deliverable_id_fkey"
            columns: ["deliverable_id"]
            isOneToOne: false
            referencedRelation: "deliverables"
            referencedColumns: ["id"]
          },
          {
            foreignKeyName: "deliverable_stage_history_deliverable_id_fkey"
            columns: ["deliverable_id"]
            isOneToOne: false
            referencedRelation: "v_deliverable_credits"
            referencedColumns: ["deliverable_id"]
          },
        ]
      }
      deliverable_types: {
        Row: {
          code: string
          consume_at_stage: string | null
          name_ar: string
          publishable: boolean
          sort: number
          stages: string[]
          unit_kind: string
        }
        Insert: {
          code: string
          consume_at_stage?: string | null
          name_ar: string
          publishable?: boolean
          sort?: number
          stages?: string[]
          unit_kind: string
        }
        Update: {
          code?: string
          consume_at_stage?: string | null
          name_ar?: string
          publishable?: boolean
          sort?: number
          stages?: string[]
          unit_kind?: string
        }
        Relationships: []
      }
      deliverables: {
        Row: {
          approved_at: string | null
          assignee_id: string | null
          client_id: string
          created_at: string
          cycle_id: string
          deliverable_type: string
          due_at: string | null
          id: string
          platforms: string[] | null
          post_url: string | null
          published_at: string | null
          revision_count: number
          script_id: string | null
          session_id: string | null
          short_code: string
          stage: string
          stage_changed_at: string
          title: string | null
          updated_by: string | null
        }
        Insert: {
          approved_at?: string | null
          assignee_id?: string | null
          client_id: string
          created_at?: string
          cycle_id: string
          deliverable_type: string
          due_at?: string | null
          id?: string
          platforms?: string[] | null
          post_url?: string | null
          published_at?: string | null
          revision_count?: number
          script_id?: string | null
          session_id?: string | null
          short_code?: string
          stage: string
          stage_changed_at?: string
          title?: string | null
          updated_by?: string | null
        }
        Update: {
          approved_at?: string | null
          assignee_id?: string | null
          client_id?: string
          created_at?: string
          cycle_id?: string
          deliverable_type?: string
          due_at?: string | null
          id?: string
          platforms?: string[] | null
          post_url?: string | null
          published_at?: string | null
          revision_count?: number
          script_id?: string | null
          session_id?: string | null
          short_code?: string
          stage?: string
          stage_changed_at?: string
          title?: string | null
          updated_by?: string | null
        }
        Relationships: [
          {
            foreignKeyName: "deliverables_assignee_id_fkey"
            columns: ["assignee_id"]
            isOneToOne: false
            referencedRelation: "staff"
            referencedColumns: ["id"]
          },
          {
            foreignKeyName: "deliverables_assignee_id_fkey"
            columns: ["assignee_id"]
            isOneToOne: false
            referencedRelation: "v_challenge_progress"
            referencedColumns: ["staff_id"]
          },
          {
            foreignKeyName: "deliverables_assignee_id_fkey"
            columns: ["assignee_id"]
            isOneToOne: false
            referencedRelation: "v_feedback_by_staff"
            referencedColumns: ["staff_id"]
          },
          {
            foreignKeyName: "deliverables_assignee_id_fkey"
            columns: ["assignee_id"]
            isOneToOne: false
            referencedRelation: "v_leaderboard"
            referencedColumns: ["staff_id"]
          },
          {
            foreignKeyName: "deliverables_client_id_fkey"
            columns: ["client_id"]
            isOneToOne: false
            referencedRelation: "clients"
            referencedColumns: ["id"]
          },
          {
            foreignKeyName: "deliverables_client_id_fkey"
            columns: ["client_id"]
            isOneToOne: false
            referencedRelation: "v_client_overview"
            referencedColumns: ["client_id"]
          },
          {
            foreignKeyName: "deliverables_cycle_id_fkey"
            columns: ["cycle_id"]
            isOneToOne: false
            referencedRelation: "cycles"
            referencedColumns: ["id"]
          },
          {
            foreignKeyName: "deliverables_cycle_id_fkey"
            columns: ["cycle_id"]
            isOneToOne: false
            referencedRelation: "v_client_overview"
            referencedColumns: ["cycle_id"]
          },
          {
            foreignKeyName: "deliverables_cycle_id_fkey"
            columns: ["cycle_id"]
            isOneToOne: false
            referencedRelation: "v_cycle_balance"
            referencedColumns: ["cycle_id"]
          },
          {
            foreignKeyName: "deliverables_deliverable_type_fkey"
            columns: ["deliverable_type"]
            isOneToOne: false
            referencedRelation: "deliverable_types"
            referencedColumns: ["code"]
          },
          {
            foreignKeyName: "deliverables_script_id_fkey"
            columns: ["script_id"]
            isOneToOne: false
            referencedRelation: "scripts"
            referencedColumns: ["id"]
          },
          {
            foreignKeyName: "deliverables_session_id_fkey"
            columns: ["session_id"]
            isOneToOne: false
            referencedRelation: "studio_sessions"
            referencedColumns: ["id"]
          },
          {
            foreignKeyName: "deliverables_session_id_fkey"
            columns: ["session_id"]
            isOneToOne: false
            referencedRelation: "v_today_sessions"
            referencedColumns: ["session_id"]
          },
          {
            foreignKeyName: "deliverables_updated_by_fkey"
            columns: ["updated_by"]
            isOneToOne: false
            referencedRelation: "staff"
            referencedColumns: ["id"]
          },
          {
            foreignKeyName: "deliverables_updated_by_fkey"
            columns: ["updated_by"]
            isOneToOne: false
            referencedRelation: "v_challenge_progress"
            referencedColumns: ["staff_id"]
          },
          {
            foreignKeyName: "deliverables_updated_by_fkey"
            columns: ["updated_by"]
            isOneToOne: false
            referencedRelation: "v_feedback_by_staff"
            referencedColumns: ["staff_id"]
          },
          {
            foreignKeyName: "deliverables_updated_by_fkey"
            columns: ["updated_by"]
            isOneToOne: false
            referencedRelation: "v_leaderboard"
            referencedColumns: ["staff_id"]
          },
        ]
      }
      feedback: {
        Row: {
          client_id: string | null
          comment: string | null
          deliverable_id: string | null
          editing_score: number | null
          filming_score: number | null
          id: string
          issues: string[] | null
          location_score: number | null
          revision_requested: boolean
          session_id: string | null
          source_ref: string | null
          submitted_at: string
          wants_changes: boolean
        }
        Insert: {
          client_id?: string | null
          comment?: string | null
          deliverable_id?: string | null
          editing_score?: number | null
          filming_score?: number | null
          id?: string
          issues?: string[] | null
          location_score?: number | null
          revision_requested?: boolean
          session_id?: string | null
          source_ref?: string | null
          submitted_at?: string
          wants_changes?: boolean
        }
        Update: {
          client_id?: string | null
          comment?: string | null
          deliverable_id?: string | null
          editing_score?: number | null
          filming_score?: number | null
          id?: string
          issues?: string[] | null
          location_score?: number | null
          revision_requested?: boolean
          session_id?: string | null
          source_ref?: string | null
          submitted_at?: string
          wants_changes?: boolean
        }
        Relationships: [
          {
            foreignKeyName: "feedback_client_id_fkey"
            columns: ["client_id"]
            isOneToOne: false
            referencedRelation: "clients"
            referencedColumns: ["id"]
          },
          {
            foreignKeyName: "feedback_client_id_fkey"
            columns: ["client_id"]
            isOneToOne: false
            referencedRelation: "v_client_overview"
            referencedColumns: ["client_id"]
          },
          {
            foreignKeyName: "feedback_deliverable_id_fkey"
            columns: ["deliverable_id"]
            isOneToOne: false
            referencedRelation: "deliverables"
            referencedColumns: ["id"]
          },
          {
            foreignKeyName: "feedback_deliverable_id_fkey"
            columns: ["deliverable_id"]
            isOneToOne: false
            referencedRelation: "v_deliverable_credits"
            referencedColumns: ["deliverable_id"]
          },
          {
            foreignKeyName: "feedback_session_id_fkey"
            columns: ["session_id"]
            isOneToOne: false
            referencedRelation: "studio_sessions"
            referencedColumns: ["id"]
          },
          {
            foreignKeyName: "feedback_session_id_fkey"
            columns: ["session_id"]
            isOneToOne: false
            referencedRelation: "v_today_sessions"
            referencedColumns: ["session_id"]
          },
        ]
      }
      invoices: {
        Row: {
          amount: number
          client_id: string
          created_at: string
          cycle_id: string | null
          description: string | null
          due_date: string
          id: string
          kind: string
          paid_amount: number
          perfex_invoice_id: number | null
          status: string
        }
        Insert: {
          amount: number
          client_id: string
          created_at?: string
          cycle_id?: string | null
          description?: string | null
          due_date: string
          id?: string
          kind: string
          paid_amount?: number
          perfex_invoice_id?: number | null
          status?: string
        }
        Update: {
          amount?: number
          client_id?: string
          created_at?: string
          cycle_id?: string | null
          description?: string | null
          due_date?: string
          id?: string
          kind?: string
          paid_amount?: number
          perfex_invoice_id?: number | null
          status?: string
        }
        Relationships: [
          {
            foreignKeyName: "invoices_client_id_fkey"
            columns: ["client_id"]
            isOneToOne: false
            referencedRelation: "clients"
            referencedColumns: ["id"]
          },
          {
            foreignKeyName: "invoices_client_id_fkey"
            columns: ["client_id"]
            isOneToOne: false
            referencedRelation: "v_client_overview"
            referencedColumns: ["client_id"]
          },
          {
            foreignKeyName: "invoices_cycle_id_fkey"
            columns: ["cycle_id"]
            isOneToOne: false
            referencedRelation: "cycles"
            referencedColumns: ["id"]
          },
          {
            foreignKeyName: "invoices_cycle_id_fkey"
            columns: ["cycle_id"]
            isOneToOne: false
            referencedRelation: "v_client_overview"
            referencedColumns: ["cycle_id"]
          },
          {
            foreignKeyName: "invoices_cycle_id_fkey"
            columns: ["cycle_id"]
            isOneToOne: false
            referencedRelation: "v_cycle_balance"
            referencedColumns: ["cycle_id"]
          },
        ]
      }
      meter_readings: {
        Row: {
          balance: number
          id: number
          kwh: number | null
          meter_id: string
          photo_path: string | null
          read_at: string
          read_by: string | null
          tier: number | null
        }
        Insert: {
          balance: number
          id?: number
          kwh?: number | null
          meter_id: string
          photo_path?: string | null
          read_at?: string
          read_by?: string | null
          tier?: number | null
        }
        Update: {
          balance?: number
          id?: number
          kwh?: number | null
          meter_id?: string
          photo_path?: string | null
          read_at?: string
          read_by?: string | null
          tier?: number | null
        }
        Relationships: [
          {
            foreignKeyName: "meter_readings_meter_id_fkey"
            columns: ["meter_id"]
            isOneToOne: false
            referencedRelation: "utility_meters"
            referencedColumns: ["id"]
          },
          {
            foreignKeyName: "meter_readings_meter_id_fkey"
            columns: ["meter_id"]
            isOneToOne: false
            referencedRelation: "v_meter_status"
            referencedColumns: ["meter_id"]
          },
          {
            foreignKeyName: "meter_readings_read_by_fkey"
            columns: ["read_by"]
            isOneToOne: false
            referencedRelation: "staff"
            referencedColumns: ["id"]
          },
          {
            foreignKeyName: "meter_readings_read_by_fkey"
            columns: ["read_by"]
            isOneToOne: false
            referencedRelation: "v_challenge_progress"
            referencedColumns: ["staff_id"]
          },
          {
            foreignKeyName: "meter_readings_read_by_fkey"
            columns: ["read_by"]
            isOneToOne: false
            referencedRelation: "v_feedback_by_staff"
            referencedColumns: ["staff_id"]
          },
          {
            foreignKeyName: "meter_readings_read_by_fkey"
            columns: ["read_by"]
            isOneToOne: false
            referencedRelation: "v_leaderboard"
            referencedColumns: ["staff_id"]
          },
        ]
      }
      meter_recharges: {
        Row: {
          amount: number
          id: number
          meter_id: string
          note: string | null
          recharged_at: string
          recorded_by: string | null
        }
        Insert: {
          amount: number
          id?: number
          meter_id: string
          note?: string | null
          recharged_at?: string
          recorded_by?: string | null
        }
        Update: {
          amount?: number
          id?: number
          meter_id?: string
          note?: string | null
          recharged_at?: string
          recorded_by?: string | null
        }
        Relationships: [
          {
            foreignKeyName: "meter_recharges_meter_id_fkey"
            columns: ["meter_id"]
            isOneToOne: false
            referencedRelation: "utility_meters"
            referencedColumns: ["id"]
          },
          {
            foreignKeyName: "meter_recharges_meter_id_fkey"
            columns: ["meter_id"]
            isOneToOne: false
            referencedRelation: "v_meter_status"
            referencedColumns: ["meter_id"]
          },
          {
            foreignKeyName: "meter_recharges_recorded_by_fkey"
            columns: ["recorded_by"]
            isOneToOne: false
            referencedRelation: "staff"
            referencedColumns: ["id"]
          },
          {
            foreignKeyName: "meter_recharges_recorded_by_fkey"
            columns: ["recorded_by"]
            isOneToOne: false
            referencedRelation: "v_challenge_progress"
            referencedColumns: ["staff_id"]
          },
          {
            foreignKeyName: "meter_recharges_recorded_by_fkey"
            columns: ["recorded_by"]
            isOneToOne: false
            referencedRelation: "v_feedback_by_staff"
            referencedColumns: ["staff_id"]
          },
          {
            foreignKeyName: "meter_recharges_recorded_by_fkey"
            columns: ["recorded_by"]
            isOneToOne: false
            referencedRelation: "v_leaderboard"
            referencedColumns: ["staff_id"]
          },
        ]
      }
      payments: {
        Row: {
          amount: number
          client_id: string | null
          id: string
          invoice_id: string
          method: string
          paid_at: string
          received_by: string | null
          reference: string | null
        }
        Insert: {
          amount: number
          client_id?: string | null
          id?: string
          invoice_id: string
          method: string
          paid_at?: string
          received_by?: string | null
          reference?: string | null
        }
        Update: {
          amount?: number
          client_id?: string | null
          id?: string
          invoice_id?: string
          method?: string
          paid_at?: string
          received_by?: string | null
          reference?: string | null
        }
        Relationships: [
          {
            foreignKeyName: "payments_client_id_fkey"
            columns: ["client_id"]
            isOneToOne: false
            referencedRelation: "clients"
            referencedColumns: ["id"]
          },
          {
            foreignKeyName: "payments_client_id_fkey"
            columns: ["client_id"]
            isOneToOne: false
            referencedRelation: "v_client_overview"
            referencedColumns: ["client_id"]
          },
          {
            foreignKeyName: "payments_invoice_id_fkey"
            columns: ["invoice_id"]
            isOneToOne: false
            referencedRelation: "invoices"
            referencedColumns: ["id"]
          },
          {
            foreignKeyName: "payments_received_by_fkey"
            columns: ["received_by"]
            isOneToOne: false
            referencedRelation: "staff"
            referencedColumns: ["id"]
          },
          {
            foreignKeyName: "payments_received_by_fkey"
            columns: ["received_by"]
            isOneToOne: false
            referencedRelation: "v_challenge_progress"
            referencedColumns: ["staff_id"]
          },
          {
            foreignKeyName: "payments_received_by_fkey"
            columns: ["received_by"]
            isOneToOne: false
            referencedRelation: "v_feedback_by_staff"
            referencedColumns: ["staff_id"]
          },
          {
            foreignKeyName: "payments_received_by_fkey"
            columns: ["received_by"]
            isOneToOne: false
            referencedRelation: "v_leaderboard"
            referencedColumns: ["staff_id"]
          },
        ]
      }
      plan_items: {
        Row: {
          deliverable_type: string
          plan_id: string
          quantity: number
        }
        Insert: {
          deliverable_type: string
          plan_id: string
          quantity: number
        }
        Update: {
          deliverable_type?: string
          plan_id?: string
          quantity?: number
        }
        Relationships: [
          {
            foreignKeyName: "plan_items_deliverable_type_fkey"
            columns: ["deliverable_type"]
            isOneToOne: false
            referencedRelation: "deliverable_types"
            referencedColumns: ["code"]
          },
          {
            foreignKeyName: "plan_items_plan_id_fkey"
            columns: ["plan_id"]
            isOneToOne: false
            referencedRelation: "plans"
            referencedColumns: ["id"]
          },
        ]
      }
      plans: {
        Row: {
          active: boolean
          base_price: number
          id: string
          name: string
          tier: number | null
          validity_days: number
        }
        Insert: {
          active?: boolean
          base_price: number
          id?: string
          name: string
          tier?: number | null
          validity_days?: number
        }
        Update: {
          active?: boolean
          base_price?: number
          id?: string
          name?: string
          tier?: number | null
          validity_days?: number
        }
        Relationships: []
      }
      publishing_schedule: {
        Row: {
          active: boolean
          client_id: string
          deliverable_type: string
          id: string
          platforms: string[] | null
          publish_time: string | null
          weekday: number
        }
        Insert: {
          active?: boolean
          client_id: string
          deliverable_type: string
          id?: string
          platforms?: string[] | null
          publish_time?: string | null
          weekday: number
        }
        Update: {
          active?: boolean
          client_id?: string
          deliverable_type?: string
          id?: string
          platforms?: string[] | null
          publish_time?: string | null
          weekday?: number
        }
        Relationships: [
          {
            foreignKeyName: "publishing_schedule_client_id_fkey"
            columns: ["client_id"]
            isOneToOne: false
            referencedRelation: "clients"
            referencedColumns: ["id"]
          },
          {
            foreignKeyName: "publishing_schedule_client_id_fkey"
            columns: ["client_id"]
            isOneToOne: false
            referencedRelation: "v_client_overview"
            referencedColumns: ["client_id"]
          },
          {
            foreignKeyName: "publishing_schedule_deliverable_type_fkey"
            columns: ["deliverable_type"]
            isOneToOne: false
            referencedRelation: "deliverable_types"
            referencedColumns: ["code"]
          },
        ]
      }
      ranks: {
        Row: {
          code: string
          icon: string
          min_xp: number
          name_ar: string
        }
        Insert: {
          code: string
          icon: string
          min_xp: number
          name_ar: string
        }
        Update: {
          code?: string
          icon?: string
          min_xp?: number
          name_ar?: string
        }
        Relationships: []
      }
      scripts: {
        Row: {
          approved_at: string | null
          approved_via: string | null
          body: string | null
          client_id: string
          created_at: string
          created_by: string | null
          id: string
          revision_count: number
          sent_at: string | null
          status: string
          title: string
        }
        Insert: {
          approved_at?: string | null
          approved_via?: string | null
          body?: string | null
          client_id: string
          created_at?: string
          created_by?: string | null
          id?: string
          revision_count?: number
          sent_at?: string | null
          status?: string
          title: string
        }
        Update: {
          approved_at?: string | null
          approved_via?: string | null
          body?: string | null
          client_id?: string
          created_at?: string
          created_by?: string | null
          id?: string
          revision_count?: number
          sent_at?: string | null
          status?: string
          title?: string
        }
        Relationships: [
          {
            foreignKeyName: "scripts_client_id_fkey"
            columns: ["client_id"]
            isOneToOne: false
            referencedRelation: "clients"
            referencedColumns: ["id"]
          },
          {
            foreignKeyName: "scripts_client_id_fkey"
            columns: ["client_id"]
            isOneToOne: false
            referencedRelation: "v_client_overview"
            referencedColumns: ["client_id"]
          },
          {
            foreignKeyName: "scripts_created_by_fkey"
            columns: ["created_by"]
            isOneToOne: false
            referencedRelation: "staff"
            referencedColumns: ["id"]
          },
          {
            foreignKeyName: "scripts_created_by_fkey"
            columns: ["created_by"]
            isOneToOne: false
            referencedRelation: "v_challenge_progress"
            referencedColumns: ["staff_id"]
          },
          {
            foreignKeyName: "scripts_created_by_fkey"
            columns: ["created_by"]
            isOneToOne: false
            referencedRelation: "v_feedback_by_staff"
            referencedColumns: ["staff_id"]
          },
          {
            foreignKeyName: "scripts_created_by_fkey"
            columns: ["created_by"]
            isOneToOne: false
            referencedRelation: "v_leaderboard"
            referencedColumns: ["staff_id"]
          },
        ]
      }
      settings: {
        Row: {
          description_ar: string | null
          key: string
          value: Json
        }
        Insert: {
          description_ar?: string | null
          key: string
          value: Json
        }
        Update: {
          description_ar?: string | null
          key?: string
          value?: Json
        }
        Relationships: []
      }
      staff: {
        Row: {
          active: boolean
          created_at: string
          email: string | null
          id: string
          name: string
          phone_wa: string | null
          roles: string[]
        }
        Insert: {
          active?: boolean
          created_at?: string
          email?: string | null
          id: string
          name: string
          phone_wa?: string | null
          roles: string[]
        }
        Update: {
          active?: boolean
          created_at?: string
          email?: string | null
          id?: string
          name?: string
          phone_wa?: string | null
          roles?: string[]
        }
        Relationships: []
      }
      studio_sessions: {
        Row: {
          actual_end: string | null
          actual_qty: number | null
          actual_start: string | null
          booked_end: string | null
          booked_start: string
          cancelled_at: string | null
          checked_in_at: string | null
          client_id: string
          confirmation: string
          created_at: string
          created_by: string | null
          cycle_id: string | null
          deliverable_type: string
          id: string
          late_cancel: boolean
          locations_required: number | null
          notes: string | null
          outfits_required: number | null
          photographer_id: string | null
          planned_qty: number
          slot: unknown
          status: string
          studio_id: string
        }
        Insert: {
          actual_end?: string | null
          actual_qty?: number | null
          actual_start?: string | null
          booked_end?: string | null
          booked_start: string
          cancelled_at?: string | null
          checked_in_at?: string | null
          client_id: string
          confirmation?: string
          created_at?: string
          created_by?: string | null
          cycle_id?: string | null
          deliverable_type?: string
          id?: string
          late_cancel?: boolean
          locations_required?: number | null
          notes?: string | null
          outfits_required?: number | null
          photographer_id?: string | null
          planned_qty: number
          slot?: unknown
          status?: string
          studio_id: string
        }
        Update: {
          actual_end?: string | null
          actual_qty?: number | null
          actual_start?: string | null
          booked_end?: string | null
          booked_start?: string
          cancelled_at?: string | null
          checked_in_at?: string | null
          client_id?: string
          confirmation?: string
          created_at?: string
          created_by?: string | null
          cycle_id?: string | null
          deliverable_type?: string
          id?: string
          late_cancel?: boolean
          locations_required?: number | null
          notes?: string | null
          outfits_required?: number | null
          photographer_id?: string | null
          planned_qty?: number
          slot?: unknown
          status?: string
          studio_id?: string
        }
        Relationships: [
          {
            foreignKeyName: "studio_sessions_client_id_fkey"
            columns: ["client_id"]
            isOneToOne: false
            referencedRelation: "clients"
            referencedColumns: ["id"]
          },
          {
            foreignKeyName: "studio_sessions_client_id_fkey"
            columns: ["client_id"]
            isOneToOne: false
            referencedRelation: "v_client_overview"
            referencedColumns: ["client_id"]
          },
          {
            foreignKeyName: "studio_sessions_created_by_fkey"
            columns: ["created_by"]
            isOneToOne: false
            referencedRelation: "staff"
            referencedColumns: ["id"]
          },
          {
            foreignKeyName: "studio_sessions_created_by_fkey"
            columns: ["created_by"]
            isOneToOne: false
            referencedRelation: "v_challenge_progress"
            referencedColumns: ["staff_id"]
          },
          {
            foreignKeyName: "studio_sessions_created_by_fkey"
            columns: ["created_by"]
            isOneToOne: false
            referencedRelation: "v_feedback_by_staff"
            referencedColumns: ["staff_id"]
          },
          {
            foreignKeyName: "studio_sessions_created_by_fkey"
            columns: ["created_by"]
            isOneToOne: false
            referencedRelation: "v_leaderboard"
            referencedColumns: ["staff_id"]
          },
          {
            foreignKeyName: "studio_sessions_cycle_id_fkey"
            columns: ["cycle_id"]
            isOneToOne: false
            referencedRelation: "cycles"
            referencedColumns: ["id"]
          },
          {
            foreignKeyName: "studio_sessions_cycle_id_fkey"
            columns: ["cycle_id"]
            isOneToOne: false
            referencedRelation: "v_client_overview"
            referencedColumns: ["cycle_id"]
          },
          {
            foreignKeyName: "studio_sessions_cycle_id_fkey"
            columns: ["cycle_id"]
            isOneToOne: false
            referencedRelation: "v_cycle_balance"
            referencedColumns: ["cycle_id"]
          },
          {
            foreignKeyName: "studio_sessions_deliverable_type_fkey"
            columns: ["deliverable_type"]
            isOneToOne: false
            referencedRelation: "deliverable_types"
            referencedColumns: ["code"]
          },
          {
            foreignKeyName: "studio_sessions_photographer_id_fkey"
            columns: ["photographer_id"]
            isOneToOne: false
            referencedRelation: "staff"
            referencedColumns: ["id"]
          },
          {
            foreignKeyName: "studio_sessions_photographer_id_fkey"
            columns: ["photographer_id"]
            isOneToOne: false
            referencedRelation: "v_challenge_progress"
            referencedColumns: ["staff_id"]
          },
          {
            foreignKeyName: "studio_sessions_photographer_id_fkey"
            columns: ["photographer_id"]
            isOneToOne: false
            referencedRelation: "v_feedback_by_staff"
            referencedColumns: ["staff_id"]
          },
          {
            foreignKeyName: "studio_sessions_photographer_id_fkey"
            columns: ["photographer_id"]
            isOneToOne: false
            referencedRelation: "v_leaderboard"
            referencedColumns: ["staff_id"]
          },
          {
            foreignKeyName: "studio_sessions_studio_id_fkey"
            columns: ["studio_id"]
            isOneToOne: false
            referencedRelation: "studios"
            referencedColumns: ["id"]
          },
        ]
      }
      studios: {
        Row: {
          active: boolean
          id: string
          name: string
        }
        Insert: {
          active?: boolean
          id?: string
          name: string
        }
        Update: {
          active?: boolean
          id?: string
          name?: string
        }
        Relationships: []
      }
      subscription_events: {
        Row: {
          client_id: string
          created_at: string
          created_by: string | null
          details: Json | null
          effective_date: string
          event_type: string
          id: number
          mrr_delta: number
          note: string | null
          subscription_id: string
        }
        Insert: {
          client_id: string
          created_at?: string
          created_by?: string | null
          details?: Json | null
          effective_date: string
          event_type: string
          id?: number
          mrr_delta?: number
          note?: string | null
          subscription_id: string
        }
        Update: {
          client_id?: string
          created_at?: string
          created_by?: string | null
          details?: Json | null
          effective_date?: string
          event_type?: string
          id?: number
          mrr_delta?: number
          note?: string | null
          subscription_id?: string
        }
        Relationships: [
          {
            foreignKeyName: "subscription_events_client_id_fkey"
            columns: ["client_id"]
            isOneToOne: false
            referencedRelation: "clients"
            referencedColumns: ["id"]
          },
          {
            foreignKeyName: "subscription_events_client_id_fkey"
            columns: ["client_id"]
            isOneToOne: false
            referencedRelation: "v_client_overview"
            referencedColumns: ["client_id"]
          },
          {
            foreignKeyName: "subscription_events_created_by_fkey"
            columns: ["created_by"]
            isOneToOne: false
            referencedRelation: "staff"
            referencedColumns: ["id"]
          },
          {
            foreignKeyName: "subscription_events_created_by_fkey"
            columns: ["created_by"]
            isOneToOne: false
            referencedRelation: "v_challenge_progress"
            referencedColumns: ["staff_id"]
          },
          {
            foreignKeyName: "subscription_events_created_by_fkey"
            columns: ["created_by"]
            isOneToOne: false
            referencedRelation: "v_feedback_by_staff"
            referencedColumns: ["staff_id"]
          },
          {
            foreignKeyName: "subscription_events_created_by_fkey"
            columns: ["created_by"]
            isOneToOne: false
            referencedRelation: "v_leaderboard"
            referencedColumns: ["staff_id"]
          },
          {
            foreignKeyName: "subscription_events_subscription_id_fkey"
            columns: ["subscription_id"]
            isOneToOne: false
            referencedRelation: "subscriptions"
            referencedColumns: ["id"]
          },
        ]
      }
      subscription_items: {
        Row: {
          deliverable_type: string
          quantity: number
          subscription_id: string
        }
        Insert: {
          deliverable_type: string
          quantity: number
          subscription_id: string
        }
        Update: {
          deliverable_type?: string
          quantity?: number
          subscription_id?: string
        }
        Relationships: [
          {
            foreignKeyName: "subscription_items_deliverable_type_fkey"
            columns: ["deliverable_type"]
            isOneToOne: false
            referencedRelation: "deliverable_types"
            referencedColumns: ["code"]
          },
          {
            foreignKeyName: "subscription_items_subscription_id_fkey"
            columns: ["subscription_id"]
            isOneToOne: false
            referencedRelation: "subscriptions"
            referencedColumns: ["id"]
          },
        ]
      }
      subscriptions: {
        Row: {
          auto_renew: boolean
          cancelled_at: string | null
          client_id: string
          created_at: string
          id: string
          name: string
          overage_rate: number | null
          plan_id: string | null
          price: number
          started_at: string
          status: string
          validity_days: number
        }
        Insert: {
          auto_renew?: boolean
          cancelled_at?: string | null
          client_id: string
          created_at?: string
          id?: string
          name: string
          overage_rate?: number | null
          plan_id?: string | null
          price: number
          started_at: string
          status?: string
          validity_days?: number
        }
        Update: {
          auto_renew?: boolean
          cancelled_at?: string | null
          client_id?: string
          created_at?: string
          id?: string
          name?: string
          overage_rate?: number | null
          plan_id?: string | null
          price?: number
          started_at?: string
          status?: string
          validity_days?: number
        }
        Relationships: [
          {
            foreignKeyName: "subscriptions_client_id_fkey"
            columns: ["client_id"]
            isOneToOne: false
            referencedRelation: "clients"
            referencedColumns: ["id"]
          },
          {
            foreignKeyName: "subscriptions_client_id_fkey"
            columns: ["client_id"]
            isOneToOne: false
            referencedRelation: "v_client_overview"
            referencedColumns: ["client_id"]
          },
          {
            foreignKeyName: "subscriptions_plan_id_fkey"
            columns: ["plan_id"]
            isOneToOne: false
            referencedRelation: "plans"
            referencedColumns: ["id"]
          },
        ]
      }
      utility_meters: {
        Row: {
          active: boolean
          id: string
          name: string
          studio_id: string | null
        }
        Insert: {
          active?: boolean
          id?: string
          name: string
          studio_id?: string | null
        }
        Update: {
          active?: boolean
          id?: string
          name?: string
          studio_id?: string | null
        }
        Relationships: [
          {
            foreignKeyName: "utility_meters_studio_id_fkey"
            columns: ["studio_id"]
            isOneToOne: false
            referencedRelation: "studios"
            referencedColumns: ["id"]
          },
        ]
      }
      xp_ledger: {
        Row: {
          challenge_id: string | null
          client_id: string | null
          created_at: string
          dedupe_key: string
          deliverable_id: string | null
          feedback_id: string | null
          id: number
          note: string | null
          points: number
          rule_code: string
          session_id: string | null
          staff_id: string
        }
        Insert: {
          challenge_id?: string | null
          client_id?: string | null
          created_at?: string
          dedupe_key: string
          deliverable_id?: string | null
          feedback_id?: string | null
          id?: number
          note?: string | null
          points: number
          rule_code: string
          session_id?: string | null
          staff_id: string
        }
        Update: {
          challenge_id?: string | null
          client_id?: string | null
          created_at?: string
          dedupe_key?: string
          deliverable_id?: string | null
          feedback_id?: string | null
          id?: number
          note?: string | null
          points?: number
          rule_code?: string
          session_id?: string | null
          staff_id?: string
        }
        Relationships: [
          {
            foreignKeyName: "xp_ledger_client_id_fkey"
            columns: ["client_id"]
            isOneToOne: false
            referencedRelation: "clients"
            referencedColumns: ["id"]
          },
          {
            foreignKeyName: "xp_ledger_client_id_fkey"
            columns: ["client_id"]
            isOneToOne: false
            referencedRelation: "v_client_overview"
            referencedColumns: ["client_id"]
          },
          {
            foreignKeyName: "xp_ledger_deliverable_id_fkey"
            columns: ["deliverable_id"]
            isOneToOne: false
            referencedRelation: "deliverables"
            referencedColumns: ["id"]
          },
          {
            foreignKeyName: "xp_ledger_deliverable_id_fkey"
            columns: ["deliverable_id"]
            isOneToOne: false
            referencedRelation: "v_deliverable_credits"
            referencedColumns: ["deliverable_id"]
          },
          {
            foreignKeyName: "xp_ledger_feedback_id_fkey"
            columns: ["feedback_id"]
            isOneToOne: false
            referencedRelation: "feedback"
            referencedColumns: ["id"]
          },
          {
            foreignKeyName: "xp_ledger_rule_code_fkey"
            columns: ["rule_code"]
            isOneToOne: false
            referencedRelation: "xp_rules"
            referencedColumns: ["code"]
          },
          {
            foreignKeyName: "xp_ledger_session_id_fkey"
            columns: ["session_id"]
            isOneToOne: false
            referencedRelation: "studio_sessions"
            referencedColumns: ["id"]
          },
          {
            foreignKeyName: "xp_ledger_session_id_fkey"
            columns: ["session_id"]
            isOneToOne: false
            referencedRelation: "v_today_sessions"
            referencedColumns: ["session_id"]
          },
          {
            foreignKeyName: "xp_ledger_staff_id_fkey"
            columns: ["staff_id"]
            isOneToOne: false
            referencedRelation: "staff"
            referencedColumns: ["id"]
          },
          {
            foreignKeyName: "xp_ledger_staff_id_fkey"
            columns: ["staff_id"]
            isOneToOne: false
            referencedRelation: "v_challenge_progress"
            referencedColumns: ["staff_id"]
          },
          {
            foreignKeyName: "xp_ledger_staff_id_fkey"
            columns: ["staff_id"]
            isOneToOne: false
            referencedRelation: "v_feedback_by_staff"
            referencedColumns: ["staff_id"]
          },
          {
            foreignKeyName: "xp_ledger_staff_id_fkey"
            columns: ["staff_id"]
            isOneToOne: false
            referencedRelation: "v_leaderboard"
            referencedColumns: ["staff_id"]
          },
        ]
      }
      xp_rules: {
        Row: {
          active: boolean
          code: string
          name_ar: string
          points: number
        }
        Insert: {
          active?: boolean
          code: string
          name_ar: string
          points: number
        }
        Update: {
          active?: boolean
          code?: string
          name_ar?: string
          points?: number
        }
        Relationships: []
      }
    }
    Views: {
      v_alert_outbox: {
        Row: {
          alert_id: number | null
          alert_key: string | null
          audience: string | null
          channel: string | null
          client_code: string | null
          client_id: string | null
          client_name: string | null
          client_phone: string | null
          created_at: string | null
          cycle_id: string | null
          deliverable_id: string | null
          email: string | null
          payload: Json | null
          phone: string | null
          priority: string | null
          recipient_name: string | null
          session_id: string | null
        }
        Relationships: [
          {
            foreignKeyName: "alerts_client_id_fkey"
            columns: ["client_id"]
            isOneToOne: false
            referencedRelation: "clients"
            referencedColumns: ["id"]
          },
          {
            foreignKeyName: "alerts_client_id_fkey"
            columns: ["client_id"]
            isOneToOne: false
            referencedRelation: "v_client_overview"
            referencedColumns: ["client_id"]
          },
          {
            foreignKeyName: "alerts_cycle_id_fkey"
            columns: ["cycle_id"]
            isOneToOne: false
            referencedRelation: "cycles"
            referencedColumns: ["id"]
          },
          {
            foreignKeyName: "alerts_cycle_id_fkey"
            columns: ["cycle_id"]
            isOneToOne: false
            referencedRelation: "v_client_overview"
            referencedColumns: ["cycle_id"]
          },
          {
            foreignKeyName: "alerts_cycle_id_fkey"
            columns: ["cycle_id"]
            isOneToOne: false
            referencedRelation: "v_cycle_balance"
            referencedColumns: ["cycle_id"]
          },
          {
            foreignKeyName: "alerts_deliverable_id_fkey"
            columns: ["deliverable_id"]
            isOneToOne: false
            referencedRelation: "deliverables"
            referencedColumns: ["id"]
          },
          {
            foreignKeyName: "alerts_deliverable_id_fkey"
            columns: ["deliverable_id"]
            isOneToOne: false
            referencedRelation: "v_deliverable_credits"
            referencedColumns: ["deliverable_id"]
          },
          {
            foreignKeyName: "alerts_session_id_fkey"
            columns: ["session_id"]
            isOneToOne: false
            referencedRelation: "studio_sessions"
            referencedColumns: ["id"]
          },
          {
            foreignKeyName: "alerts_session_id_fkey"
            columns: ["session_id"]
            isOneToOne: false
            referencedRelation: "v_today_sessions"
            referencedColumns: ["session_id"]
          },
        ]
      }
      v_challenge_progress: {
        Row: {
          challenge_id: string | null
          completed: boolean | null
          completed_at: string | null
          description_ar: string | null
          ends_on: string | null
          metric: string | null
          name_ar: string | null
          progress: number | null
          progress_pct: number | null
          reward_xp: number | null
          staff_id: string | null
          staff_name: string | null
          starts_on: string | null
          target: number | null
        }
        Relationships: []
      }
      v_client_overview: {
        Row: {
          account_manager: string | null
          balances: Json | null
          client_id: string | null
          code: string | null
          cycle_id: string | null
          days_left: number | null
          end_date: string | null
          health_score: number | null
          missed_slots_30d: number | null
          name: string | null
          outstanding: number | null
          overdue_invoices: number | null
          satisfaction_60d: number | null
          start_date: string | null
          status: string | null
          time_ratio: number | null
          total_paid: number | null
          usage_ratio: number | null
        }
        Relationships: []
      }
      v_complaints: {
        Row: {
          alert_id: number | null
          client_code: string | null
          client_id: string | null
          client_name: string | null
          client_phone: string | null
          comment: string | null
          created_at: string | null
          dedupe_key: string | null
          deliverable_id: string | null
          editing_score: number | null
          editor: string | null
          escalated: boolean | null
          filming_score: number | null
          handled_at: string | null
          handled_by: string | null
          handled_by_name: string | null
          is_open: boolean | null
          issues: Json | null
          location_score: number | null
          photographer: string | null
          post_url: string | null
          resolution: string | null
          wait_minutes: number | null
          within_sla: boolean | null
        }
        Relationships: [
          {
            foreignKeyName: "alerts_client_id_fkey"
            columns: ["client_id"]
            isOneToOne: false
            referencedRelation: "clients"
            referencedColumns: ["id"]
          },
          {
            foreignKeyName: "alerts_client_id_fkey"
            columns: ["client_id"]
            isOneToOne: false
            referencedRelation: "v_client_overview"
            referencedColumns: ["client_id"]
          },
          {
            foreignKeyName: "alerts_deliverable_id_fkey"
            columns: ["deliverable_id"]
            isOneToOne: false
            referencedRelation: "deliverables"
            referencedColumns: ["id"]
          },
          {
            foreignKeyName: "alerts_deliverable_id_fkey"
            columns: ["deliverable_id"]
            isOneToOne: false
            referencedRelation: "v_deliverable_credits"
            referencedColumns: ["deliverable_id"]
          },
          {
            foreignKeyName: "alerts_handled_by_fkey"
            columns: ["handled_by"]
            isOneToOne: false
            referencedRelation: "staff"
            referencedColumns: ["id"]
          },
          {
            foreignKeyName: "alerts_handled_by_fkey"
            columns: ["handled_by"]
            isOneToOne: false
            referencedRelation: "v_challenge_progress"
            referencedColumns: ["staff_id"]
          },
          {
            foreignKeyName: "alerts_handled_by_fkey"
            columns: ["handled_by"]
            isOneToOne: false
            referencedRelation: "v_feedback_by_staff"
            referencedColumns: ["staff_id"]
          },
          {
            foreignKeyName: "alerts_handled_by_fkey"
            columns: ["handled_by"]
            isOneToOne: false
            referencedRelation: "v_leaderboard"
            referencedColumns: ["staff_id"]
          },
        ]
      }
      v_content_runway: {
        Row: {
          client_code: string | null
          client_id: string | null
          client_name: string | null
          deliverable_type: string | null
          first_gap_date: string | null
          in_pipeline: number | null
          next_booked_session: string | null
          ready: number | null
          shoot_deadline: string | null
          slots_next_7d: number | null
        }
        Relationships: [
          {
            foreignKeyName: "publishing_schedule_client_id_fkey"
            columns: ["client_id"]
            isOneToOne: false
            referencedRelation: "clients"
            referencedColumns: ["id"]
          },
          {
            foreignKeyName: "publishing_schedule_client_id_fkey"
            columns: ["client_id"]
            isOneToOne: false
            referencedRelation: "v_client_overview"
            referencedColumns: ["client_id"]
          },
          {
            foreignKeyName: "publishing_schedule_deliverable_type_fkey"
            columns: ["deliverable_type"]
            isOneToOne: false
            referencedRelation: "deliverable_types"
            referencedColumns: ["code"]
          },
        ]
      }
      v_cycle_balance: {
        Row: {
          client_id: string | null
          client_name: string | null
          cycle_id: string | null
          cycle_status: string | null
          days_left: number | null
          deliverable_type: string | null
          end_date: string | null
          name_ar: string | null
          remaining: number | null
          reserved: number | null
          start_date: string | null
          subscription_id: string | null
          time_elapsed_pct: number | null
          total: number | null
          unit_kind: string | null
          used: number | null
          used_pct: number | null
        }
        Relationships: [
          {
            foreignKeyName: "cycle_entitlements_deliverable_type_fkey"
            columns: ["deliverable_type"]
            isOneToOne: false
            referencedRelation: "deliverable_types"
            referencedColumns: ["code"]
          },
          {
            foreignKeyName: "cycles_client_id_fkey"
            columns: ["client_id"]
            isOneToOne: false
            referencedRelation: "clients"
            referencedColumns: ["id"]
          },
          {
            foreignKeyName: "cycles_client_id_fkey"
            columns: ["client_id"]
            isOneToOne: false
            referencedRelation: "v_client_overview"
            referencedColumns: ["client_id"]
          },
          {
            foreignKeyName: "cycles_subscription_id_fkey"
            columns: ["subscription_id"]
            isOneToOne: false
            referencedRelation: "subscriptions"
            referencedColumns: ["id"]
          },
        ]
      }
      v_dashboard_kpis: {
        Row: {
          active_clients: number | null
          arpa: number | null
          collected_this_month: number | null
          forfeiture_pct_3m: number | null
          grr_pct: number | null
          logo_churn_pct: number | null
          mrr: number | null
          nrr_pct: number | null
          outstanding_amount: number | null
          overdue_amount: number | null
        }
        Relationships: []
      }
      v_deliverable_credits: {
        Row: {
          assignee_id: string | null
          client_id: string | null
          client_name: string | null
          created_at: string | null
          deliverable_id: string | null
          deliverable_type: string | null
          editing_score: number | null
          editor: string | null
          feedback_at: string | null
          feedback_comment: string | null
          filming_score: number | null
          location_score: number | null
          photographer: string | null
          photographer_id: string | null
          post_url: string | null
          published_at: string | null
          publisher: string | null
          publisher_id: string | null
          revision_count: number | null
          stage: string | null
          title: string | null
        }
        Relationships: [
          {
            foreignKeyName: "deliverable_stage_history_changed_by_fkey"
            columns: ["publisher_id"]
            isOneToOne: false
            referencedRelation: "staff"
            referencedColumns: ["id"]
          },
          {
            foreignKeyName: "deliverable_stage_history_changed_by_fkey"
            columns: ["publisher_id"]
            isOneToOne: false
            referencedRelation: "v_challenge_progress"
            referencedColumns: ["staff_id"]
          },
          {
            foreignKeyName: "deliverable_stage_history_changed_by_fkey"
            columns: ["publisher_id"]
            isOneToOne: false
            referencedRelation: "v_feedback_by_staff"
            referencedColumns: ["staff_id"]
          },
          {
            foreignKeyName: "deliverable_stage_history_changed_by_fkey"
            columns: ["publisher_id"]
            isOneToOne: false
            referencedRelation: "v_leaderboard"
            referencedColumns: ["staff_id"]
          },
          {
            foreignKeyName: "deliverables_assignee_id_fkey"
            columns: ["assignee_id"]
            isOneToOne: false
            referencedRelation: "staff"
            referencedColumns: ["id"]
          },
          {
            foreignKeyName: "deliverables_assignee_id_fkey"
            columns: ["assignee_id"]
            isOneToOne: false
            referencedRelation: "v_challenge_progress"
            referencedColumns: ["staff_id"]
          },
          {
            foreignKeyName: "deliverables_assignee_id_fkey"
            columns: ["assignee_id"]
            isOneToOne: false
            referencedRelation: "v_feedback_by_staff"
            referencedColumns: ["staff_id"]
          },
          {
            foreignKeyName: "deliverables_assignee_id_fkey"
            columns: ["assignee_id"]
            isOneToOne: false
            referencedRelation: "v_leaderboard"
            referencedColumns: ["staff_id"]
          },
          {
            foreignKeyName: "deliverables_client_id_fkey"
            columns: ["client_id"]
            isOneToOne: false
            referencedRelation: "clients"
            referencedColumns: ["id"]
          },
          {
            foreignKeyName: "deliverables_client_id_fkey"
            columns: ["client_id"]
            isOneToOne: false
            referencedRelation: "v_client_overview"
            referencedColumns: ["client_id"]
          },
          {
            foreignKeyName: "deliverables_deliverable_type_fkey"
            columns: ["deliverable_type"]
            isOneToOne: false
            referencedRelation: "deliverable_types"
            referencedColumns: ["code"]
          },
          {
            foreignKeyName: "studio_sessions_photographer_id_fkey"
            columns: ["photographer_id"]
            isOneToOne: false
            referencedRelation: "staff"
            referencedColumns: ["id"]
          },
          {
            foreignKeyName: "studio_sessions_photographer_id_fkey"
            columns: ["photographer_id"]
            isOneToOne: false
            referencedRelation: "v_challenge_progress"
            referencedColumns: ["staff_id"]
          },
          {
            foreignKeyName: "studio_sessions_photographer_id_fkey"
            columns: ["photographer_id"]
            isOneToOne: false
            referencedRelation: "v_feedback_by_staff"
            referencedColumns: ["staff_id"]
          },
          {
            foreignKeyName: "studio_sessions_photographer_id_fkey"
            columns: ["photographer_id"]
            isOneToOne: false
            referencedRelation: "v_leaderboard"
            referencedColumns: ["staff_id"]
          },
        ]
      }
      v_feedback_by_staff: {
        Row: {
          avg_editing: number | null
          avg_filming: number | null
          month: string | null
          name: string | null
          responses: number | null
          roles: string[] | null
          staff_id: string | null
        }
        Relationships: []
      }
      v_leaderboard: {
        Row: {
          expected_bonus: number | null
          month: string | null
          name: string | null
          next_rank_xp: number | null
          position: number | null
          rank_code: string | null
          rank_icon: string | null
          rank_name: string | null
          roles: string[] | null
          staff_id: string | null
          xp_month: number | null
          xp_total: number | null
        }
        Relationships: []
      }
      v_meter_status: {
        Row: {
          balance_after_recharges: number | null
          balance_now_est: number | null
          daily_burn: number | null
          days_left: number | null
          last_balance: number | null
          last_read_at: string | null
          meter_id: string | null
          name: string | null
          next_session: string | null
          photo_path: string | null
          runs_out_at: string | null
          runs_out_before_next_session: boolean | null
          studio_id: string | null
          tier: number | null
        }
        Relationships: [
          {
            foreignKeyName: "utility_meters_studio_id_fkey"
            columns: ["studio_id"]
            isOneToOne: false
            referencedRelation: "studios"
            referencedColumns: ["id"]
          },
        ]
      }
      v_mrr_monthly: {
        Row: {
          active_clients: number | null
          arpa: number | null
          month: string | null
          mrr: number | null
        }
        Relationships: []
      }
      v_retention_monthly: {
        Row: {
          churned_clients: number | null
          clients_start: number | null
          grr_pct: number | null
          logo_churn_pct: number | null
          month: string | null
          mrr_start: number | null
          nrr_pct: number | null
        }
        Relationships: []
      }
      v_schedule_package_check: {
        Row: {
          client_id: string | null
          client_name: string | null
          deliverable_type: string | null
          diff: number | null
          needed_by_schedule: number | null
          package_qty: number | null
          per_week: number | null
        }
        Relationships: [
          {
            foreignKeyName: "publishing_schedule_client_id_fkey"
            columns: ["client_id"]
            isOneToOne: false
            referencedRelation: "clients"
            referencedColumns: ["id"]
          },
          {
            foreignKeyName: "publishing_schedule_client_id_fkey"
            columns: ["client_id"]
            isOneToOne: false
            referencedRelation: "v_client_overview"
            referencedColumns: ["client_id"]
          },
          {
            foreignKeyName: "publishing_schedule_deliverable_type_fkey"
            columns: ["deliverable_type"]
            isOneToOne: false
            referencedRelation: "deliverable_types"
            referencedColumns: ["code"]
          },
        ]
      }
      v_slot_board: {
        Row: {
          board_status: string | null
          client_code: string | null
          client_id: string | null
          client_name: string | null
          deliverable_id: string | null
          deliverable_stage: string | null
          deliverable_title: string | null
          deliverable_type: string | null
          post_url: string | null
          publish_time: string | null
          slot_date: string | null
          slot_id: string | null
          status: string | null
        }
        Relationships: [
          {
            foreignKeyName: "content_slots_client_id_fkey"
            columns: ["client_id"]
            isOneToOne: false
            referencedRelation: "clients"
            referencedColumns: ["id"]
          },
          {
            foreignKeyName: "content_slots_client_id_fkey"
            columns: ["client_id"]
            isOneToOne: false
            referencedRelation: "v_client_overview"
            referencedColumns: ["client_id"]
          },
          {
            foreignKeyName: "content_slots_deliverable_id_fkey"
            columns: ["deliverable_id"]
            isOneToOne: false
            referencedRelation: "deliverables"
            referencedColumns: ["id"]
          },
          {
            foreignKeyName: "content_slots_deliverable_id_fkey"
            columns: ["deliverable_id"]
            isOneToOne: false
            referencedRelation: "v_deliverable_credits"
            referencedColumns: ["deliverable_id"]
          },
          {
            foreignKeyName: "content_slots_deliverable_type_fkey"
            columns: ["deliverable_type"]
            isOneToOne: false
            referencedRelation: "deliverable_types"
            referencedColumns: ["code"]
          },
        ]
      }
      v_staff_email_outbox: {
        Row: {
          alert_id: number | null
          alert_key: string | null
          audience: string | null
          client_code: string | null
          client_id: string | null
          client_name: string | null
          created_at: string | null
          email: string | null
          payload: Json | null
          priority: string | null
          recipient_name: string | null
        }
        Relationships: [
          {
            foreignKeyName: "alerts_client_id_fkey"
            columns: ["client_id"]
            isOneToOne: false
            referencedRelation: "clients"
            referencedColumns: ["id"]
          },
          {
            foreignKeyName: "alerts_client_id_fkey"
            columns: ["client_id"]
            isOneToOne: false
            referencedRelation: "v_client_overview"
            referencedColumns: ["client_id"]
          },
        ]
      }
      v_today_sessions: {
        Row: {
          approved_scripts_unused: number | null
          booked_end: string | null
          booked_start: string | null
          client_name: string | null
          client_phone: string | null
          confirmation: string | null
          deliverable_type: string | null
          first_session: boolean | null
          locations_required: number | null
          outfits_required: number | null
          photographer: string | null
          photographer_phone: string | null
          planned_qty: number | null
          session_id: string | null
          status: string | null
          studio: string | null
        }
        Relationships: [
          {
            foreignKeyName: "studio_sessions_deliverable_type_fkey"
            columns: ["deliverable_type"]
            isOneToOne: false
            referencedRelation: "deliverable_types"
            referencedColumns: ["code"]
          },
        ]
      }
      v_utilization_monthly: {
        Row: {
          deliverable_type: string | null
          forfeited: number | null
          forfeiture_pct: number | null
          month: string | null
          total: number | null
          used: number | null
          utilization_pct: number | null
        }
        Relationships: [
          {
            foreignKeyName: "cycle_entitlements_deliverable_type_fkey"
            columns: ["deliverable_type"]
            isOneToOne: false
            referencedRelation: "deliverable_types"
            referencedColumns: ["code"]
          },
        ]
      }
    }
    Functions: {
      add_staff: {
        Args: {
          p_email: string
          p_name: string
          p_phone?: string
          p_roles: string[]
        }
        Returns: string
      }
      add_topup: {
        Args: {
          p_by?: string
          p_cycle: string
          p_price: number
          p_qty: number
          p_type: string
        }
        Returns: string
      }
      assign_ready_content: { Args: never; Returns: number }
      award_xp: {
        Args: {
          p_challenge?: string
          p_client?: string
          p_dedupe: string
          p_deliverable?: string
          p_feedback?: string
          p_note?: string
          p_points?: number
          p_rule: string
          p_session?: string
          p_staff: string
        }
        Returns: number
      }
      cairo_today: { Args: never; Returns: string }
      cancel_session: {
        Args: { p_by?: string; p_reason?: string; p_session: string }
        Returns: boolean
      }
      cancel_subscription: {
        Args: { p_by?: string; p_reason?: string; p_sub: string }
        Returns: undefined
      }
      challenge_metric: {
        Args: {
          p_from: string
          p_metric: string
          p_staff: string
          p_to: string
        }
        Returns: number
      }
      change_subscription: {
        Args: {
          p_by?: string
          p_event: string
          p_new_items?: Json
          p_new_price: number
          p_new_validity?: number
          p_note?: string
          p_sub: string
        }
        Returns: undefined
      }
      check_challenges: { Args: never; Returns: number }
      check_in_session: { Args: { p_session: string }; Returns: undefined }
      check_power: { Args: never; Returns: number }
      checklist_missing: {
        Args: {
          p_deliverable: string
          p_session: string
          p_stages: string[]
          p_type: string
        }
        Returns: string[]
      }
      claim_staff_emails: {
        Args: { p_limit?: number }
        Returns: {
          alert_id: number
          alert_key: string
          client_id: string
          client_name: string
          email: string
          payload: Json
          priority: string
          recipient_name: string
        }[]
      }
      claim_wa_messages: {
        Args: { p_limit?: number }
        Returns: {
          alert_id: number
          alert_key: string
          balance_text: string
          client_code: string
          client_id: string
          client_name: string
          cycle_end: string
          cycle_id: string
          deliverable_id: string
          destination: string
          feedback_code: string
          payload: Json
          priority: string
        }[]
      }
      close_expired_cycles: { Args: never; Returns: number }
      complete_session: {
        Args: {
          p_actual_end?: string
          p_actual_qty?: number
          p_actual_start?: string
          p_by?: string
          p_session: string
        }
        Returns: number
      }
      create_cycle: {
        Args: { p_start: string; p_sub: string }
        Returns: string
      }
      create_subscription: {
        Args: {
          p_by?: string
          p_client: string
          p_items: Json
          p_name: string
          p_overage_rate?: number
          p_plan?: string
          p_price: number
          p_start?: string
          p_validity_days?: number
        }
        Returns: string
      }
      escalate_complaints: { Args: never; Returns: number }
      feedback_redirect: { Args: { p_code: string }; Returns: string }
      generate_alerts: { Args: never; Returns: number }
      generate_content_slots: { Args: { p_days?: number }; Returns: number }
      generate_session_reminders: { Args: never; Returns: number }
      handle_wa_reply: {
        Args: { p_chat: string; p_sender: string; p_text: string }
        Returns: Json
      }
      has_role: { Args: { p_roles: string[] }; Returns: boolean }
      is_staff: { Args: never; Returns: boolean }
      mark_alert_failed: {
        Args: { p_alert: number; p_error: string }
        Returns: undefined
      }
      mark_missed_slots: { Args: never; Returns: number }
      mark_no_show: {
        Args: { p_by?: string; p_session: string }
        Returns: number
      }
      my_roles: { Args: never; Returns: string[] }
      prepare_monthly_bonuses: { Args: { p_month?: string }; Returns: number }
      preview_booking: {
        Args: {
          p_client: string
          p_qty: number
          p_start?: string
          p_type: string
        }
        Returns: Json
      }
      queue_alert: {
        Args: {
          p_audiences: string[]
          p_client: string
          p_cycle?: string
          p_dedupe: string
          p_deliverable?: string
          p_key: string
          p_payload?: Json
          p_priority?: string
          p_session?: string
          p_staff?: string
        }
        Returns: number
      }
      record_meter_reading: {
        Args: {
          p_balance: number
          p_kwh?: number
          p_meter: string
          p_photo?: string
          p_tier?: number
        }
        Returns: Json
      }
      renew_due_subscriptions: { Args: never; Returns: number }
      require_role: { Args: { p_roles: string[] }; Returns: undefined }
      resolve_alert: {
        Args: { p_alert: number; p_by?: string; p_resolution: string }
        Returns: undefined
      }
      run_daily: { Args: never; Returns: Json }
      run_hourly: { Args: never; Returns: Json }
      schedule_package_diff: {
        Args: { p_days: number; p_items: Json; p_per_week: Json }
        Returns: Json
      }
      send_client_message: {
        Args: { p_client: string; p_key: string }
        Returns: number
      }
      session_locations: { Args: { p_qty: number }; Returns: number }
      session_minutes: {
        Args: { p_qty: number; p_unit: string }
        Returns: number
      }
      session_outfits: { Args: { p_qty: number }; Returns: number }
      set_session_confirmation: {
        Args: { p_confirmation: string; p_session: string }
        Returns: undefined
      }
      setting_num: { Args: { k: string }; Returns: number }
      setting_text: { Args: { k: string }; Returns: string }
    }
    Enums: {
      [_ in never]: never
    }
    CompositeTypes: {
      [_ in never]: never
    }
  }
}

type DatabaseWithoutInternals = Omit<Database, "__InternalSupabase">

type DefaultSchema = DatabaseWithoutInternals[Extract<keyof Database, "public">]

export type Tables<
  DefaultSchemaTableNameOrOptions extends
    | keyof (DefaultSchema["Tables"] & DefaultSchema["Views"])
    | { schema: keyof DatabaseWithoutInternals },
  TableName extends (DefaultSchemaTableNameOrOptions extends {
    schema: keyof DatabaseWithoutInternals
  }
    ? keyof (DatabaseWithoutInternals[DefaultSchemaTableNameOrOptions["schema"]]["Tables"] &
        DatabaseWithoutInternals[DefaultSchemaTableNameOrOptions["schema"]]["Views"])
    : never) = never,
> = DefaultSchemaTableNameOrOptions extends {
  schema: keyof DatabaseWithoutInternals
}
  ? (DatabaseWithoutInternals[DefaultSchemaTableNameOrOptions["schema"]]["Tables"] &
      DatabaseWithoutInternals[DefaultSchemaTableNameOrOptions["schema"]]["Views"])[TableName] extends {
      Row: infer R
    }
    ? R
    : never
  : DefaultSchemaTableNameOrOptions extends keyof (DefaultSchema["Tables"] &
        DefaultSchema["Views"])
    ? (DefaultSchema["Tables"] &
        DefaultSchema["Views"])[DefaultSchemaTableNameOrOptions] extends {
        Row: infer R
      }
      ? R
      : never
    : never

export type TablesInsert<
  DefaultSchemaTableNameOrOptions extends
    | keyof DefaultSchema["Tables"]
    | { schema: keyof DatabaseWithoutInternals },
  TableName extends (DefaultSchemaTableNameOrOptions extends {
    schema: keyof DatabaseWithoutInternals
  }
    ? keyof DatabaseWithoutInternals[DefaultSchemaTableNameOrOptions["schema"]]["Tables"]
    : never) = never,
> = DefaultSchemaTableNameOrOptions extends {
  schema: keyof DatabaseWithoutInternals
}
  ? DatabaseWithoutInternals[DefaultSchemaTableNameOrOptions["schema"]]["Tables"][TableName] extends {
      Insert: infer I
    }
    ? I
    : never
  : DefaultSchemaTableNameOrOptions extends keyof DefaultSchema["Tables"]
    ? DefaultSchema["Tables"][DefaultSchemaTableNameOrOptions] extends {
        Insert: infer I
      }
      ? I
      : never
    : never

export type TablesUpdate<
  DefaultSchemaTableNameOrOptions extends
    | keyof DefaultSchema["Tables"]
    | { schema: keyof DatabaseWithoutInternals },
  TableName extends (DefaultSchemaTableNameOrOptions extends {
    schema: keyof DatabaseWithoutInternals
  }
    ? keyof DatabaseWithoutInternals[DefaultSchemaTableNameOrOptions["schema"]]["Tables"]
    : never) = never,
> = DefaultSchemaTableNameOrOptions extends {
  schema: keyof DatabaseWithoutInternals
}
  ? DatabaseWithoutInternals[DefaultSchemaTableNameOrOptions["schema"]]["Tables"][TableName] extends {
      Update: infer U
    }
    ? U
    : never
  : DefaultSchemaTableNameOrOptions extends keyof DefaultSchema["Tables"]
    ? DefaultSchema["Tables"][DefaultSchemaTableNameOrOptions] extends {
        Update: infer U
      }
      ? U
      : never
    : never

export type Enums<
  DefaultSchemaEnumNameOrOptions extends
    | keyof DefaultSchema["Enums"]
    | { schema: keyof DatabaseWithoutInternals },
  EnumName extends (DefaultSchemaEnumNameOrOptions extends {
    schema: keyof DatabaseWithoutInternals
  }
    ? keyof DatabaseWithoutInternals[DefaultSchemaEnumNameOrOptions["schema"]]["Enums"]
    : never) = never,
> = DefaultSchemaEnumNameOrOptions extends {
  schema: keyof DatabaseWithoutInternals
}
  ? DatabaseWithoutInternals[DefaultSchemaEnumNameOrOptions["schema"]]["Enums"][EnumName]
  : DefaultSchemaEnumNameOrOptions extends keyof DefaultSchema["Enums"]
    ? DefaultSchema["Enums"][DefaultSchemaEnumNameOrOptions]
    : never

export type CompositeTypes<
  PublicCompositeTypeNameOrOptions extends
    | keyof DefaultSchema["CompositeTypes"]
    | { schema: keyof DatabaseWithoutInternals },
  CompositeTypeName extends (PublicCompositeTypeNameOrOptions extends {
    schema: keyof DatabaseWithoutInternals
  }
    ? keyof DatabaseWithoutInternals[PublicCompositeTypeNameOrOptions["schema"]]["CompositeTypes"]
    : never) = never,
> = PublicCompositeTypeNameOrOptions extends {
  schema: keyof DatabaseWithoutInternals
}
  ? DatabaseWithoutInternals[PublicCompositeTypeNameOrOptions["schema"]]["CompositeTypes"][CompositeTypeName]
  : PublicCompositeTypeNameOrOptions extends keyof DefaultSchema["CompositeTypes"]
    ? DefaultSchema["CompositeTypes"][PublicCompositeTypeNameOrOptions]
    : never

export const Constants = {
  public: {
    Enums: {},
  },
} as const
